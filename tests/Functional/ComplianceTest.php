<?php

namespace App\Tests\Functional;

use App\Entity\Commande;
use App\Entity\User;
use App\Legal\LegalVersion;
use App\Service\Boutique\CommandeService;
use App\Service\ProductionChecklist;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** Garde-fous de conformité : consentements, factures, mentions légales, durées de conservation. */
final class ComplianceTest extends DatabaseTestCase
{
    public function testOrderWithoutAcceptingTheTermsIsRefused(): void
    {
        $variante = $this->createArticle('Polo', 2500, [5])->getVariantes()->first();
        $this->loginAs(['ROLE_FAMILLE']);
        $crawler = $this->client->request('GET', '/boutique/article/'.$variante->getArticle()->getSlug());
        $token = $crawler->filter('form[action$="/boutique/panier/ajouter"] input[name=_token]')->attr('value');
        $this->client->request('POST', '/boutique/panier/ajouter', ['_token' => $token, 'variante' => $variante->getId(), 'quantite' => 1]);

        $crawler = $this->client->request('GET', '/boutique/commande');
        self::assertStringContainsString('Commander avec obligation de paiement', (string) $this->client->getResponse()->getContent());
        $form = $crawler->filter('form[name=checkout]')->form([
            'checkout[prenom]' => 'Alice',
            'checkout[nom]' => 'Martin',
            'checkout[email]' => 'sans-cgv@test.local',
            'checkout[modePaiement]' => Commande::PAIEMENT_ESPECES,
        ]);
        $this->client->submit($form);

        self::assertNull($this->em->getRepository(Commande::class)->findOneBy(['email' => 'sans-cgv@test.local']), 'Aucune commande sans acceptation des conditions.');
        self::assertStringContainsString('accepter les conditions de vente', (string) $this->client->getResponse()->getContent());
    }

    public function testRegistrationRequiresConsentAndRecordsIt(): void
    {
        $values = [
            'registration[nom]' => 'Dupont',
            'registration[email]' => 'nouveau-client@test.local',
            'registration[plainPassword][first]' => 'Mot-de-passe-solide-1',
            'registration[plainPassword][second]' => 'Mot-de-passe-solide-1',
        ];
        $crawler = $this->client->request('GET', '/mon-compte/inscription');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form($values));
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'nouveau-client@test.local']));

        $crawler = $this->client->request('GET', '/mon-compte/inscription');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form($values + ['registration[consent]' => true]));

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nouveau-client@test.local']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getConsentAt());
        self::assertSame(LegalVersion::CURRENT, $user->getConsentVersion());
    }

    public function testInvoiceIsNumberedOnPaymentAndIsSequential(): void
    {
        $service = self::getContainer()->get(CommandeService::class);
        $first = $this->createCommande('A1');
        $second = $this->createCommande('A2');

        $this->client->request('GET', sprintf('/boutique/commande/%s/%s/facture', $first->getReference(), $first->getToken()));
        self::assertResponseStatusCodeSame(404, 'Pas de facture avant le règlement.');

        $service->appliquer($first, 'payer');
        $service->appliquer($second, 'payer');
        $service->appliquer($first, 'payer'); // idempotent : le numéro ne change pas

        $year = date('Y');
        self::assertSame(sprintf('FAC-%s-00001', $year), $first->getNumeroFacture());
        self::assertSame(sprintf('FAC-%s-00002', $year), $second->getNumeroFacture());

        $this->client->request('GET', sprintf('/boutique/commande/%s/%s/facture', $first->getReference(), $first->getToken()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', (string) $first->getNumeroFacture());
        $this->client->request('GET', sprintf('/boutique/commande/%s/mauvais-jeton/facture', $first->getReference()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testLegalPagesShowTheWithdrawalAndRetentionInformation(): void
    {
        $this->client->request('GET', '/conditions-de-vente');
        $cgv = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Formulaire type de rétractation', $cgv);
        self::assertStringContainsString('sans frais de retour', $cgv);
        self::assertStringContainsString('médiateur de la consommation', $cgv);
        self::assertStringContainsString('version '.LegalVersion::CURRENT, $cgv);

        $this->client->request('GET', '/politique-de-confidentialite');
        $privacy = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Conservation', $privacy);
        self::assertStringContainsString("Droit à l'image", $privacy);
    }

    public function testContactMapIsNotLoadedBeforeTheVisitorAsksForIt(): void
    {
        $this->client->request('GET', '/contact');

        self::assertSelectorNotExists('iframe', 'Aucune requête vers OpenStreetMap avant le clic.');
        self::assertSelectorExists('#carte-osm-load');
    }

    public function testProductionChecklistFlagsMissingLegalNotices(): void
    {
        $checks = self::getContainer()->get(ProductionChecklist::class)->run();
        $labels = array_column(array_filter($checks, static fn (array $c): bool => !$c['ok']), 'label');

        self::assertContains('Mentions légales : hébergeur (LEGAL_HOST)', $labels);
        self::assertContains('Conditions de vente : médiateur de la consommation (LEGAL_CONSUMER_MEDIATOR)', $labels);
    }

    public function testInactiveAccountsAreAnonymizedButActiveOnesAreKept(): void
    {
        $inactive = $this->createUser([], 'inactif@test.local');
        $inactive->setLastLoginAt(new \DateTimeImmutable('-40 months'));
        $recent = $this->createUser([], 'actif@test.local');
        $recent->setLastLoginAt(new \DateTimeImmutable('-2 months'));
        $staff = $this->createUser(['ROLE_ADMIN'], 'equipe@test.local');
        $staff->setLastLoginAt(new \DateTimeImmutable('-60 months'));
        $this->em->flush();
        $ids = [$inactive->getId(), $recent->getId(), $staff->getId()];

        $tester = new CommandTester((new Application(self::$kernel))->find('app:rgpd:purger-inactifs'));
        $tester->execute(['--dry-run' => true]);
        self::assertStringContainsString('inactif@test.local', $tester->getDisplay());
        self::assertFalse($this->em->find(User::class, $ids[0])->isAnonymized(), 'Le mode simulation ne modifie rien.');

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->em->clear();

        self::assertTrue($this->em->find(User::class, $ids[0])->isAnonymized());
        self::assertFalse($this->em->find(User::class, $ids[1])->isAnonymized());
        self::assertFalse($this->em->find(User::class, $ids[2])->isAnonymized(), "Un compte de l'équipe n'est jamais anonymisé automatiquement.");
    }

    private function createCommande(string $suffix): Commande
    {
        $commande = (new Commande())
            ->setReference('ESC-TEST-'.$suffix)
            ->setPrenom('Alice')->setNom('Martin')->setEmail(strtolower($suffix).'@test.local')
            ->setModePaiement(Commande::PAIEMENT_ESPECES);
        $this->em->persist($commande);
        $this->em->flush();

        return $commande;
    }
}

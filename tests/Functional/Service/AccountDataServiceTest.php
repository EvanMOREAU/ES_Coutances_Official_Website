<?php

namespace App\Tests\Functional\Service;

use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Service\Privacy\AccountDataService;
use App\Tests\Support\DatabaseTestCase;

final class AccountDataServiceTest extends DatabaseTestCase
{
    private AccountDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = static::getContainer()->get(AccountDataService::class);
    }

    public function testExportContainsTheAccountButNeverItsSecrets(): void
    {
        $user = $this->createUser(['ROLE_FAMILLE'], 'rgpd@test.local')->setTotpSecret('TOPSECRET')->setBackupCodes([password_hash('code', PASSWORD_BCRYPT)]);
        $this->em->flush();

        $export = $this->service->export($user);
        $json = json_encode($export, JSON_UNESCAPED_UNICODE) ?: '';

        self::assertArrayHasKey('Compte', $export);
        self::assertStringContainsString('rgpd@test.local', $json);
        self::assertStringNotContainsString('TOPSECRET', $json);
        self::assertStringNotContainsString((string) $user->getPassword(), $json, 'Le mot de passe haché ne doit jamais sortir.');
        self::assertStringNotContainsString('backupCodes', $json);
        self::assertSame('activée', $export['Compte']['Double authentification']['Application']);
    }

    public function testExportIncludesFamilyLicenseesAndOrders(): void
    {
        $saison = $this->createSaison('2099-2100', '2099-07-01', '2100-06-30', true);
        $licencie = $this->createLicencie($saison, 'Rgpd', 'Camille');
        $parent = $licencie->getFamille()->getUser();
        $variante = $this->createArticle('Polo', 2500, [4])->getVariantes()->first();
        $commande = (new Commande())->setReference('ESC-261007-RGPD')->setPrenom('Camille')->setNom('Rgpd')->setEmail('c@test.local')->setModePaiement(Commande::PAIEMENT_ESPECES)->setUser($parent);
        $commande->addLigne(CommandeLigne::depuis($variante, 1));
        $this->em->persist($commande);
        $this->em->flush();
        $parentId = $parent->getId();
        $this->em->clear(); // comme en production : les relations sont relues depuis la base

        $export = $this->service->export($this->em->find(User::class, $parentId));
        $json = json_encode($export, JSON_UNESCAPED_UNICODE) ?: '';

        self::assertArrayHasKey('Famille', $export);
        self::assertArrayHasKey('Licenciés', $export);
        self::assertArrayHasKey('Commandes de la boutique', $export);
        self::assertStringContainsString('Camille', $json);
        self::assertStringContainsString('ESC-261007-RGPD', $json);
        self::assertStringNotContainsString((string) $commande->getToken(), $json, 'Le jeton de suivi de commande est un secret.');
    }

    public function testStaffAndAlreadyAnonymizedAccountsCannotBeAnonymized(): void
    {
        $staff = $this->createUser(['ROLE_EDITOR']);
        self::assertFalse($this->service->canAnonymize($staff));

        $done = $this->createUser(['ROLE_FAMILLE'])->markAnonymized();
        self::assertFalse($this->service->canAnonymize($done));

        $this->expectException(\LogicException::class);
        $this->service->anonymize($staff);
    }

    public function testAnonymizationErasesPersonalDataButKeepsTheInvoices(): void
    {
        $saison = $this->createSaison('2099-2100', '2099-07-01', '2100-06-30', true);
        $licencie = $this->createLicencie($saison, 'Effacer', 'Moi', '2012-05-20');
        $famille = $licencie->getFamille();
        $famille->setAdresse('12 rue Privée')->setTelephone('0611223344');
        $parent = $famille->getUser()->setPrenom('Parent')->setBio('ma bio');
        $variante = $this->createArticle('Echarpe', 1800, [3])->getVariantes()->first();
        $commande = (new Commande())->setReference('ESC-261007-GONE')->setPrenom('Parent')->setNom('Effacer')->setEmail('parent@test.local')->setTelephone('0611223344')->setModePaiement(Commande::PAIEMENT_ESPECES)->setUser($parent);
        $commande->addLigne(CommandeLigne::depuis($variante, 2));
        $this->em->persist($commande);
        $this->em->flush();
        $parentId = $parent->getId();
        $licencieId = $licencie->getId();
        $familleId = $famille->getId();
        $commandeId = $commande->getId();
        $this->em->clear(); // comme en production : les relations sont relues depuis la base

        $this->service->anonymize($this->em->find(User::class, $parentId));
        $this->em->clear();

        $parent = $this->em->find(User::class, $parentId);
        self::assertTrue($parent->isAnonymized());
        self::assertSame(sprintf('anonyme-%d@anonymise.invalid', $parentId), $parent->getEmail());
        self::assertNull($parent->getPrenom());
        self::assertNull($parent->getBio());
        self::assertSame(['ROLE_USER'], $parent->getRoles(), 'Plus aucun rôle : le compte ne sert plus à rien.');

        $famille = $this->em->find(Famille::class, $familleId);
        self::assertSame('Famille anonymisée', $famille->getNom());
        self::assertNull($famille->getAdresse());
        self::assertNull($famille->getTelephone());

        $licencie = $this->em->find(Licencie::class, $licencieId);
        self::assertSame('Anonymisé', $licencie->getNom());
        self::assertSame('2012-01-01', $licencie->getDateNaissance()->format('Y-m-d'), 'Seule l\'année de naissance est conservée.');
        self::assertFalse($licencie->isActif());
        self::assertTrue($this->em->find(User::class, $licencie->getUser()->getId())->isAnonymized(), 'Le compte du licencié est anonymisé avec la famille.');

        $commande = $this->em->find(Commande::class, $commandeId);
        self::assertSame('anonyme@anonymise.invalid', $commande->getEmail());
        self::assertNull($commande->getTelephone());
        self::assertNull($commande->getUser());
        self::assertSame(3600, $commande->getTotalCentimes(), 'Les montants comptables sont conservés.');
        self::assertSame(2, $commande->getNombreArticles());
    }

    public function testAnonymizedAccountCanNoLongerLogIn(): void
    {
        $user = $this->createUser(['ROLE_FAMILLE'], 'partie@test.local');
        $this->service->anonymize($user);

        $crawler = $this->client->request('GET', '/mon-compte/connexion');
        $this->client->submit($crawler->filter('form[method=post]')->first()->form(['email' => 'partie@test.local', 'password' => self::PASSWORD]));
        $this->client->request('GET', '/mon-compte');

        self::assertResponseRedirects();
        self::assertStringContainsString('/mon-compte/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }
}

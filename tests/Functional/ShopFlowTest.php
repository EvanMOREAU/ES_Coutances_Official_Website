<?php

namespace App\Tests\Functional;

use App\Entity\ArticleVariante;
use App\Entity\Commande;
use App\Service\Boutique\Panier;
use App\Tests\Support\DatabaseTestCase;

final class ShopFlowTest extends DatabaseTestCase
{
    private function addToCart(ArticleVariante $variante, int $quantite = 1): void
    {
        $crawler = $this->client->request('GET', '/boutique/article/'.$variante->getArticle()->getSlug());
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('form[action$="/boutique/panier/ajouter"] input[name=_token]')->attr('value');
        $this->client->request('POST', '/boutique/panier/ajouter', ['_token' => $token, 'variante' => $variante->getId(), 'quantite' => $quantite]);
    }

    public function testCatalogueListsOnlyActiveArticles(): void
    {
        $visible = $this->createArticle('Maillot visible');
        $hidden = $this->createArticle('Maillot masqué');
        $hidden->setActif(false);
        $this->em->flush();

        $this->client->request('GET', '/boutique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Maillot visible');
        self::assertSelectorTextNotContains('body', 'Maillot masqué');

        $this->client->request('GET', '/boutique/article/'.$visible->getSlug());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/boutique/article/'.$hidden->getSlug());
        self::assertResponseStatusCodeSame(404);
    }

    public function testCartLifecycle(): void
    {
        $variante = $this->createArticle('Écharpe', 1500, [8])->getVariantes()->first();

        $this->addToCart($variante, 2);
        self::assertResponseRedirects('/boutique/panier');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Écharpe');
        self::assertStringContainsString('30,00', (string) $this->client->getResponse()->getContent(), 'Le total (2 × 15 €) est affiché.');

        $token = $crawler->filter('input[name=_token]')->attr('value');
        $this->client->request('POST', '/boutique/panier/modifier', ['_token' => $token, 'variante' => $variante->getId(), 'quantite' => 3]);
        $this->client->request('GET', '/boutique/panier');
        self::assertStringContainsString('45,00', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', '/boutique/panier/retirer', ['_token' => $token, 'variante' => $variante->getId()]);
        $this->client->request('GET', '/boutique/panier');
        self::assertSelectorTextNotContains('body', 'Écharpe');
    }

    public function testCartQuantityIsCappedByStockAndByTheMaximum(): void
    {
        $limited = $this->createArticle('Stock limité', 1000, [2])->getVariantes()->first();
        $plenty = $this->createArticle('Beaucoup', 1000, [500])->getVariantes()->first();

        $this->addToCart($limited, 5);
        $this->addToCart($plenty, 99);
        $this->client->request('GET', '/boutique/panier');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('20,00', $content, 'Limité à 2 exemplaires (le stock).');
        self::assertStringContainsString(number_format(Panier::QUANTITE_MAX * 10, 2, ',', ''), $content, 'Limité à la quantité maximale.');
    }

    public function testCartActionsRequireAValidCsrfToken(): void
    {
        $variante = $this->createArticle()->getVariantes()->first();

        $this->client->request('POST', '/boutique/panier/ajouter', ['_token' => 'faux', 'variante' => $variante->getId(), 'quantite' => 1]);
        $this->client->request('GET', '/boutique/panier');

        self::assertSelectorTextContains('body', 'vide');
    }

    public function testCheckoutRequiresAnAccount(): void
    {
        $variante = $this->createArticle()->getVariantes()->first();
        $this->addToCart($variante);

        $this->client->request('GET', '/boutique/commande');

        self::assertResponseRedirects();
        self::assertStringContainsString('/mon-compte/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testCheckoutWithEmptyCartGoesBackToTheCart(): void
    {
        $this->loginAs(['ROLE_FAMILLE']);

        $this->client->request('GET', '/boutique/commande');

        self::assertResponseRedirects('/boutique/panier');
    }

    public function testCashOrderIsPlacedAndStockDecremented(): void
    {
        $variante = $this->createArticle('Polo', 2500, [5])->getVariantes()->first();
        $user = $this->loginAs(['ROLE_FAMILLE']);
        $this->addToCart($variante, 2);

        $crawler = $this->client->request('GET', '/boutique/commande');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name=checkout]')->form([
            'checkout[prenom]' => 'Alice',
            'checkout[nom]' => 'Martin',
            'checkout[email]' => 'alice@test.local',
            'checkout[modePaiement]' => Commande::PAIEMENT_ESPECES,
            'checkout[cgv]' => true,
        ]);
        $this->client->submit($form);

        $commande = $this->em->getRepository(Commande::class)->findOneBy(['email' => 'alice@test.local']);
        self::assertNotNull($commande);
        self::assertSame(5000, $commande->getTotalCentimes());
        self::assertNotNull($commande->getCgvAcceptedAt());
        self::assertSame(\App\Legal\LegalVersion::CURRENT, $commande->getCgvVersion());
        self::assertSame($user->getId(), $commande->getUser()?->getId());
        self::assertResponseRedirects(sprintf('/boutique/commande/%s/%s', $commande->getReference(), $commande->getToken()));
        self::assertSame(3, $this->em->find(ArticleVariante::class, $variante->getId())->getStock());
        self::assertEmailCount(1);

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', (string) $commande->getReference());
    }

    public function testOrderTrackingNeedsTheSecretToken(): void
    {
        $variante = $this->createArticle()->getVariantes()->first();
        $commande = (new Commande())->setReference('ESC-261007-TEST')->setPrenom('A')->setNom('B')->setEmail('a@test.local')->setModePaiement(Commande::PAIEMENT_ESPECES);
        $commande->addLigne(\App\Entity\CommandeLigne::depuis($variante, 1));
        $this->em->persist($commande);
        $this->em->flush();

        $this->client->request('GET', '/boutique/commande/ESC-261007-TEST/'.$commande->getToken());
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/boutique/commande/ESC-261007-TEST/mauvais-jeton');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/boutique/commande/ESC-000000-NOPE/'.$commande->getToken());
        self::assertResponseStatusCodeSame(404);
    }

    public function testHelloAssoNotificationIgnoresGarbage(): void
    {
        foreach (['', 'pas du json', '{}', '{"data":{"checkoutIntentId":999999}}', '[1,2,3]'] as $body) {
            $this->client->request('POST', '/boutique/helloasso/notification', [], [], ['CONTENT_TYPE' => 'application/json'], $body);

            self::assertResponseIsSuccessful(sprintf('Corps « %s » : la notification doit être acquittée.', $body));
        }
    }

    public function testPromoCodeCheckEndpoint(): void
    {
        $this->client->request('GET', '/boutique/code-promo/verifier?code=inconnu');

        self::assertResponseIsSuccessful();
        self::assertJson((string) $this->client->getResponse()->getContent());
    }
}

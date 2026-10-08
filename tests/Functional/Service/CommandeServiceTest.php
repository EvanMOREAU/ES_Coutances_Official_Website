<?php

namespace App\Tests\Functional\Service;

use App\Entity\ArticleVariante;
use App\Entity\CodePromo;
use App\Entity\Commande;
use App\Service\Boutique\CommandeService;
use App\Service\Boutique\PromoCodeException;
use App\Service\Boutique\StockInsuffisantException;
use App\Tests\Support\DatabaseTestCase;

final class CommandeServiceTest extends DatabaseTestCase
{
    private CommandeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = static::getContainer()->get(CommandeService::class);
    }

    /** @return array<string, mixed> */
    private function client(array $extra = []): array
    {
        return $extra + [
            'prenom' => 'Marie', 'nom' => 'Durand', 'email' => 'marie@example.test', 'telephone' => '',
            'note' => '', 'modePaiement' => Commande::PAIEMENT_ESPECES,
        ];
    }

    private function variante(int $stock = 5, int $prix = 2000): ArticleVariante
    {
        return $this->createArticle('Écharpe', $prix, [$stock])->getVariantes()->first();
    }

    public function testPlacingAnOrderDecrementsStockAndSnapshotsPrices(): void
    {
        $variante = $this->variante(5, 2000);

        $commande = $this->service->passer([$variante->getId() => 2], $this->client(), null);

        self::assertNotNull($commande->getId());
        self::assertMatchesRegularExpression('/^ESC-\d{6}-[0-9A-F]{4}$/', (string) $commande->getReference());
        self::assertSame(4000, $commande->getTotalCentimes());
        self::assertSame(Commande::STATUT_NOUVELLE, $commande->getStatut());
        self::assertSame(3, $variante->getStock());
        self::assertNull($commande->getTelephone(), 'Un téléphone vide est enregistré comme absent.');
    }

    public function testEmptyCartIsRefused(): void
    {
        $this->expectException(StockInsuffisantException::class);

        $this->service->passer([], $this->client(), null);
    }

    public function testNotEnoughStockIsRefusedAndNothingIsChanged(): void
    {
        $variante = $this->variante(1);

        try {
            $this->service->passer([$variante->getId() => 3], $this->client(), null);
            self::fail('Une commande supérieure au stock doit être refusée.');
        } catch (StockInsuffisantException $e) {
            self::assertStringContainsString('Il ne reste que 1 exemplaire', $e->getMessage());
        }

        self::assertSame(1, $variante->getStock(), 'Un refus ne doit pas toucher au stock.');
    }

    public function testSoldOutArticleIsRefused(): void
    {
        $variante = $this->variante(0);

        $this->expectException(StockInsuffisantException::class);
        $this->expectExceptionMessageMatches('/épuisé/');

        $this->service->passer([$variante->getId() => 1], $this->client(), null);
    }

    public function testArchivedArticleCannotBeOrdered(): void
    {
        $variante = $this->variante();
        $variante->getArticle()->setActif(false);
        $this->em->flush();

        $this->expectException(StockInsuffisantException::class);

        $this->service->passer([$variante->getId() => 1], $this->client(), null);
    }

    public function testUnknownVariantIsRefused(): void
    {
        $this->expectException(StockInsuffisantException::class);

        $this->service->passer([999999 => 1], $this->client(), null);
    }

    public function testCancellingGivesTheStockBack(): void
    {
        $variante = $this->variante(5);
        $commande = $this->service->passer([$variante->getId() => 4], $this->client(), null);
        self::assertSame(1, $variante->getStock());

        $this->service->appliquer($commande, 'annuler');

        self::assertSame(Commande::STATUT_ANNULEE, $commande->getStatut());
        self::assertSame(5, $variante->getStock());
    }

    public function testCancelledOrderCannotBeCancelledOrPaidAgain(): void
    {
        $commande = $this->service->passer([$this->variante()->getId() => 1], $this->client(), null);
        $this->service->appliquer($commande, 'annuler');

        foreach (['annuler', 'payer', 'preparer', 'retirer'] as $action) {
            try {
                $this->service->appliquer($commande, $action);
                self::fail(sprintf('« %s » ne doit pas être possible sur une commande annulée.', $action));
            } catch (\DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNormalLifecycle(): void
    {
        $commande = $this->service->passer([$this->variante()->getId() => 1], $this->client(), null);

        $this->service->appliquer($commande, 'preparer');
        self::assertSame(Commande::STATUT_PRETE, $commande->getStatut());

        $this->service->appliquer($commande, 'retirer');
        self::assertSame(Commande::STATUT_RETIREE, $commande->getStatut());
        self::assertTrue($commande->isPayee(), 'La remise au client encaisse le règlement.');

        $this->expectException(\DomainException::class);
        $this->service->appliquer($commande, 'annuler');
    }

    public function testPreparingTwiceIsRefused(): void
    {
        $commande = $this->service->passer([$this->variante()->getId() => 1], $this->client(), null);
        $this->service->appliquer($commande, 'preparer');

        $this->expectException(\DomainException::class);
        $this->service->appliquer($commande, 'preparer');
    }

    public function testUnknownActionIsRefused(): void
    {
        $commande = $this->service->passer([$this->variante()->getId() => 1], $this->client(), null);

        $this->expectException(\DomainException::class);
        $this->service->appliquer($commande, 'rembourser');
    }

    private function savePromo(?string $code = 'BON-LIVRAISON'): CodePromo
    {
        $promo = (new CodePromo())->setCode($code)->setApprouve(true);
        $this->em->persist($promo);
        $this->em->flush();

        return $promo;
    }

    public function testDeliveryVoucherNeedsAFullAddress(): void
    {
        $this->savePromo();

        $this->expectException(PromoCodeException::class);
        $this->expectExceptionMessageMatches('/adresse de livraison complète/');

        $this->service->passer([$this->variante()->getId() => 1], $this->client(['codePromo' => 'BON-LIVRAISON']), null);
    }

    public function testDeliveryVoucherIsAppliedWithAddress(): void
    {
        $promo = $this->savePromo();

        $commande = $this->service->passer([$this->variante()->getId() => 1], $this->client([
            'codePromo' => 'BON-LIVRAISON', 'livraisonAdresse' => '1 rue du Test', 'livraisonCodePostal' => '50200', 'livraisonVille' => 'Coutances',
        ]), null);

        self::assertSame($promo, $commande->getCodePromo());
        self::assertSame(1, $promo->getUsageActuel());
        self::assertTrue($commande->isLivraisonDemandee());
        self::assertSame('Coutances', $commande->getLivraisonVille());
    }

    public function testUnknownVoucherIsRefused(): void
    {
        $this->expectException(PromoCodeException::class);

        $this->service->passer([$this->variante()->getId() => 1], $this->client(['codePromo' => 'INCONNU']), null);
    }

    public function testExpiredVoucherIsRefused(): void
    {
        $this->savePromo('PERIME')->setDateFin(new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $this->expectException(PromoCodeException::class);

        $this->service->passer([$this->variante()->getId() => 1], $this->client(['codePromo' => 'PERIME']), null);
    }

    public function testVoucherReservedToAnotherAccountIsRefused(): void
    {
        $promo = $this->savePromo()->setUtilisateur($this->createUser(['ROLE_FAMILLE']));
        $this->em->flush();

        $this->expectException(PromoCodeException::class);

        $this->service->passer([$this->variante()->getId() => 1], $this->client(['codePromo' => $promo->getCode()]), $this->createUser(['ROLE_FAMILLE']));
    }
}

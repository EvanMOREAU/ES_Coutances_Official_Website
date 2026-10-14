<?php

namespace App\Tests\Functional;

use App\Entity\Adhesion;
use App\Entity\AuditLog;
use App\Entity\Reglement;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdhesionExportTest extends DatabaseTestCase
{
    private function createAdhesion(string $saisonLibelle, string $nom, bool $withPayment): Adhesion
    {
        $saison   = $this->createSaison($saisonLibelle, '2026-07-01', '2027-06-30', true);
        $licencie = $this->createLicencie($saison, $nom, 'Camille');
        $adhesion = (new Adhesion())->setLicencie($licencie)->setSaison($saison)->setMontantBaseCentimes(15000);
        if ($withPayment) {
            $adhesion->addReglement((new Reglement())->setMode(Reglement::MODE_CHEQUE)->setMontantCentimes(5000)->setOrdre(1)->setDateEcheance(new \DateTimeImmutable('2026-09-15'))->setRecu(true)->setReference('CHQ-001'));
        }
        $this->em->persist($adhesion);
        $this->em->flush();

        return $adhesion;
    }

    private function csv(string $url): string
    {
        $this->client->request('GET', $url);
        self::assertInstanceOf(StreamedResponse::class, $this->client->getResponse());

        // Le client de test capture le flux dans la réponse interne.
        return (string) $this->client->getInternalResponse()->getContent();
    }

    public function testExportListsOneRowPerPaymentWithAccountingAmounts(): void
    {
        $this->createAdhesion('2026-2027', 'Durand', true);
        $this->loginAsDev();

        $content = $this->csv('/admin/paiements/export.csv');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content, 'BOM UTF-8 pour qu\'Excel lise les accents.');
        self::assertStringContainsString('Saison;Licencié;Famille', $content);
        self::assertStringContainsString('2026-2027;"Camille Durand"', $content);
        self::assertStringContainsString('150,00', $content);
        self::assertStringContainsString('50,00;oui', $content);
        self::assertStringContainsString('CHQ-001', $content);
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testLicenceWithoutPaymentStillAppearsOnce(): void
    {
        $this->createAdhesion('2026-2027', 'Martin', false);
        $this->loginAsDev();

        $content = $this->csv('/admin/paiements/export.csv');

        self::assertSame(1, substr_count($content, 'Martin'));
    }

    public function testExportCanBeFilteredBySeason(): void
    {
        $courante = $this->createAdhesion('2026-2027', 'Durand', true);
        $this->loginAsDev();
        $autre = $this->createSaison('2024-2025', '2024-07-01', '2025-06-30');

        $content = $this->csv('/admin/paiements/export.csv?saison='.$autre->getId());

        self::assertStringNotContainsString('Durand', $content);
        self::assertNotNull($courante->getId());
    }

    public function testExportIsRecordedInTheJournal(): void
    {
        $this->createAdhesion('2026-2027', 'Durand', true);
        $this->loginAsDev();

        $this->csv('/admin/paiements/export.csv');

        $logs = $this->em->getRepository(AuditLog::class)->findBy(['category' => 'Paiements']);
        self::assertNotSame([], $logs, 'Chaque export comptable doit être consigné.');
    }

    public function testExportNeedsTheRightToSeePayments(): void
    {
        $this->loginAs(['ROLE_EDITOR']);

        $this->client->request('GET', '/admin/paiements/export.csv');

        self::assertResponseStatusCodeSame(403);
    }
}

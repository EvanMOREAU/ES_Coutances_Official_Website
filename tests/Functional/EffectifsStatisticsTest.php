<?php

namespace App\Tests\Functional;

use App\Entity\Adhesion;
use App\Entity\Licencie;
use App\Entity\Saison;
use App\Service\EffectifsStatistics;
use App\Tests\Support\DatabaseTestCase;

final class EffectifsStatisticsTest extends DatabaseTestCase
{
    private function adhesion(Licencie $licencie, Saison $saison): void
    {
        $this->em->persist((new Adhesion())->setLicencie($licencie)->setSaison($saison)->setMontantBaseCentimes(15000));
        $this->em->flush();
    }

    /** @return array{0: Saison, 1: Saison} */
    private function seasonsWithPeople(): array
    {
        $precedente = $this->createSaison('2025-2026', '2025-07-01', '2026-06-30');
        $courante   = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);

        // Camille a une licence les deux saisons, Hugo seulement l'an dernier, Lina seulement cette saison.
        $camille = $this->createLicencie($courante, 'Durand', 'Camille', '2014-04-12');
        $hugo    = $this->createLicencie($precedente, 'Martin', 'Hugo', '2014-05-01');
        $lina    = $this->createLicencie($courante, 'Petit', 'Lina', '2012-09-30');
        $this->adhesion($camille, $precedente);
        $this->adhesion($camille, $courante);
        $this->adhesion($hugo, $precedente);
        $this->adhesion($lina, $courante);

        return [$precedente, $courante];
    }

    public function testEvolutionReinscriptionAndCategories(): void
    {
        [$precedente, $courante] = $this->seasonsWithPeople();

        $stats = static::getContainer()->get(EffectifsStatistics::class)->pour($courante);

        self::assertSame($precedente->getId(), $stats['precedente']?->getId());
        self::assertSame(2, $stats['total']);
        self::assertSame(2, $stats['totalPrecedent']);
        self::assertSame(0.0, $stats['evolution']);
        self::assertSame(1, $stats['reinscrits']);
        self::assertSame(1, $stats['nouveaux']);
        self::assertSame(1, $stats['partants']);
        self::assertSame(50.0, $stats['tauxReinscription']);
        self::assertSame($stats['total'], array_sum(array_column($stats['categories'], 'actuel')));
        self::assertSame($stats['totalPrecedent'], array_sum(array_column($stats['categories'], 'precedent')));
    }

    public function testCategoryIsTheOneOfEachSeason(): void
    {
        [, $courante] = $this->seasonsWithPeople();

        $stats = static::getContainer()->get(EffectifsStatistics::class)->pour($courante);
        $byCategory = array_column($stats['categories'], null, 'categorie');

        // Camille (née en 2014) : U13 en 2026-2027, U12 en 2025-2026 (âge atteint en fin de saison).
        self::assertSame(1, $byCategory['U13']['actuel']);
        self::assertSame(2, $byCategory['U12']['precedent'], 'Camille et Hugo, nés en 2014.');
    }

    public function testFirstSeasonHasNoComparison(): void
    {
        $courante = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $this->createLicencie($courante, 'Durand', 'Camille');

        $stats = static::getContainer()->get(EffectifsStatistics::class)->pour($courante);

        self::assertNull($stats['precedente']);
        self::assertNull($stats['evolution']);
        self::assertNull($stats['tauxReinscription']);
        self::assertSame(1, $stats['total']);
    }

    public function testArchivedLicenseesAreNotCounted(): void
    {
        $courante = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $licencie = $this->createLicencie($courante, 'Durand', 'Camille');
        $licencie->setStatut(Licencie::STATUT_ARCHIVED);
        $this->em->flush();

        self::assertSame(0, static::getContainer()->get(EffectifsStatistics::class)->pour($courante)['total']);
    }

    public function testPageShowsTheFiguresAndNeedsPermission(): void
    {
        [, $courante] = $this->seasonsWithPeople();

        $this->loginAs(['ROLE_EDITOR']);
        $this->client->request('GET', '/admin/statistiques-effectifs');
        self::assertResponseStatusCodeSame(403);

        $this->loginAsDev();
        $this->client->request('GET', '/admin/statistiques-effectifs?saison='.$courante->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Statistiques d\'effectifs');
        self::assertSelectorTextContains('body', 'Réinscription');
        self::assertSelectorTextContains('body', '50,0 %');
    }
}

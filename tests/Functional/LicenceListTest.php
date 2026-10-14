<?php

namespace App\Tests\Functional;

use App\Entity\Saison;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;

final class LicenceListTest extends DatabaseTestCase
{
    public function testListDefaultsToTheCurrentSeasonAndCanShowAll(): void
    {
        $actuelle = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $ancienne = $this->createSaison('2024-2025', '2024-07-01', '2025-06-30');
        $this->createLicencie($actuelle, 'Durand', 'Camille');
        $this->createLicencie($ancienne, 'Bernard', 'Hugo');
        $this->loginAsDev();

        $this->client->request('GET', '/admin/licencies');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Camille');
        self::assertSelectorTextNotContains('table', 'Hugo');

        $this->client->request('GET', '/admin/licencies?saison=toutes');
        self::assertSelectorTextContains('table', 'Camille');
        self::assertSelectorTextContains('table', 'Hugo');

        $this->client->request('GET', '/admin/licencies?saison='.$ancienne->getId());
        self::assertSelectorTextContains('table', 'Hugo');
        self::assertSelectorTextNotContains('table', 'Camille');
    }

    public function testListLoadsRelationsWithoutOneQueryPerRow(): void
    {
        $saisonId = (int) $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true)->getId();
        $this->createLicencie($this->em->getReference(Saison::class, $saisonId), 'Un', 'Premier');
        $this->loginAsDev();
        $one = $this->queriesFor('/admin/licencies');

        for ($i = 0; $i < 6; ++$i) {
            $this->createLicencie($this->em->getReference(Saison::class, $saisonId), 'Nom'.$i, 'Prenom'.$i);
        }
        $six = $this->queriesFor('/admin/licencies');

        self::assertLessThanOrEqual($one + 3, $six, 'Le nombre de requêtes ne doit pas croître avec le nombre de licenciés (N+1).');
    }

    private function queriesFor(string $url): int
    {
        $this->em->clear();
        $this->client->enableProfiler();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'Le profileur doit être actif pour compter les requêtes.');

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}

<?php

namespace App\Tests\Functional\Service;

use App\Entity\Licencie;
use App\Service\SaisonCloture;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SaisonClotureTest extends DatabaseTestCase
{
    public function testOnlyLicenseesOfFinishedSeasonsAreArchived(): void
    {
        $passee = $this->createSaison('2023-2024', '2023-07-01', '2024-06-30');
        $encours = $this->createSaison('2099-2100', '2099-07-01', '2100-06-30', true);
        $ancien = $this->createLicencie($passee, 'Ancien');
        $actuel = $this->createLicencie($encours, 'Actuel');

        $archives = static::getContainer()->get(SaisonCloture::class)->cloturer();

        self::assertGreaterThanOrEqual(1, $archives);
        $this->em->refresh($ancien);
        $this->em->refresh($actuel);
        self::assertSame(Licencie::STATUT_ARCHIVED, $ancien->getStatut());
        self::assertSame(Licencie::STATUT_ACTIVE, $actuel->getStatut());
    }

    public function testRunningTheClosureTwiceChangesNothingTheSecondTime(): void
    {
        $this->createLicencie($this->createSaison('2023-2024', '2023-07-01', '2024-06-30'));
        $service = static::getContainer()->get(SaisonCloture::class);

        $service->cloturer();

        self::assertSame(0, $service->cloturer());
    }

    public function testConsoleCommandReportsTheCount(): void
    {
        $this->createLicencie($this->createSaison('2023-2024', '2023-07-01', '2024-06-30'));
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:saison:cloturer'));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/\d+ licencié\(s\) archivé\(s\)/', $tester->getDisplay());
    }
}

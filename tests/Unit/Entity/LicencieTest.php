<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Saison;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class LicencieTest extends TestCase
{
    private function saison(string $fin): Saison
    {
        return (new Saison())
            ->setLibelle('Saison')
            ->setDateDebut(new \DateTimeImmutable('2025-07-01'))
            ->setDateFin(new \DateTimeImmutable($fin));
    }

    public function testLicenceIsCurrentWhileTheSeasonIsNotOver(): void
    {
        $licencie = (new Licencie())->setSaison($this->saison('+30 days'));

        self::assertTrue($licencie->isActif());
        self::assertFalse($licencie->isSaisonTerminee());
        self::assertTrue($licencie->isEnCours());
    }

    public function testLicenceEndsWithTheSeason(): void
    {
        $licencie = (new Licencie())->setSaison($this->saison('-1 day'));

        self::assertTrue($licencie->isSaisonTerminee());
        self::assertFalse($licencie->isEnCours());
    }

    public function testLastDayOfTheSeasonIsStillCurrent(): void
    {
        $licencie = (new Licencie())->setSaison($this->saison('today'));

        self::assertFalse($licencie->isSaisonTerminee());
    }

    public function testLicenceWithoutSeasonIsNeverOver(): void
    {
        self::assertFalse((new Licencie())->isSaisonTerminee());
    }

    public function testCategoryUsesBirthYearAndShift(): void
    {
        $licencie = (new Licencie())
            ->setSaison($this->saison('2026-06-30'))
            ->setDateNaissance(new \DateTimeImmutable('2014-05-01'));

        self::assertSame('U12', $licencie->getCategorieNaturelle());
        self::assertSame('U12', $licencie->getCategorie());

        $licencie->setDecalageCategorie(1);
        self::assertSame('U12', $licencie->getCategorieNaturelle());
        self::assertSame('U13', $licencie->getCategorie());
    }

    public function testCategoryIsNullWithoutBirthDate(): void
    {
        self::assertNull((new Licencie())->getCategorie());
        self::assertNull((new Licencie())->getCategorieNaturelle());
    }

    public function testAutonomousLicenseeIsTheirOwnFamilyHolder(): void
    {
        $user = (new User())->setEmail('adulte@test.local');
        $famille = (new Famille())->setUser($user);

        self::assertTrue((new Licencie())->setUser($user)->setFamille($famille)->isAutonome());
        self::assertFalse((new Licencie())->setUser($user)->isAutonome());
        self::assertFalse((new Licencie())->setUser((new User())->setEmail('enfant@test.local'))->setFamille($famille)->isAutonome());
    }
}

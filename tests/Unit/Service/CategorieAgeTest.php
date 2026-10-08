<?php

namespace App\Tests\Unit\Service;

use App\Entity\Saison;
use App\Service\CategorieAge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategorieAgeTest extends TestCase
{
    #[DataProvider('ages')]
    public function testFromAge(int $age, string $expected): void
    {
        self::assertSame($expected, CategorieAge::fromAge($age));
    }

    /** @return iterable<string, array{int, string}> */
    public static function ages(): iterable
    {
        yield 'sous le minimum' => [3, 'U6'];
        yield 'minimum' => [6, 'U6'];
        yield 'milieu' => [13, 'U13'];
        yield 'dernier jeune' => [19, 'U19'];
        yield 'senior' => [20, 'Senior'];
        yield 'très âgé' => [45, 'Senior'];
    }

    public function testCalculerUsesSeasonEndYear(): void
    {
        $saison = (new Saison())
            ->setLibelle('2025-2026')
            ->setDateDebut(new \DateTimeImmutable('2025-07-01'))
            ->setDateFin(new \DateTimeImmutable('2026-06-30'));

        self::assertSame(2026, CategorieAge::anneeSaisonFin($saison));
        self::assertSame('U13', CategorieAge::calculer(new \DateTimeImmutable('2013-03-10'), $saison));
    }

    public function testCalculerAppliesShift(): void
    {
        $saison = (new Saison())
            ->setLibelle('2025-2026')
            ->setDateDebut(new \DateTimeImmutable('2025-07-01'))
            ->setDateFin(new \DateTimeImmutable('2026-06-30'));
        $naissance = new \DateTimeImmutable('2013-03-10');

        self::assertSame('U14', CategorieAge::calculer($naissance, $saison, 1));
        self::assertSame('U12', CategorieAge::calculer($naissance, $saison, -1));
    }

    public function testAnneeSaisonFinWithoutSeasonFollowsFootballCalendar(): void
    {
        $now = new \DateTimeImmutable();
        $expected = (int) $now->format('Y') + ((int) $now->format('n') >= 7 ? 1 : 0);

        self::assertSame($expected, CategorieAge::anneeSaisonFin());
    }

    public function testChoices(): void
    {
        $choices = CategorieAge::choices();

        self::assertSame('U6', array_key_first($choices));
        self::assertSame('Senior', array_key_last($choices));
        self::assertCount(CategorieAge::AGE_SENIOR - CategorieAge::AGE_MIN + 1, $choices);
        self::assertContains(0, CategorieAge::decalageChoices());
    }
}

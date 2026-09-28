<?php

namespace App\Service;

use App\Entity\Saison;

/**
 * Calcul de la catégorie d'âge d'un licencié (U6, U7 ... U19, Senior).
 *
 * Règle appliquée : âge = année de fin de saison - année de naissance
 * (l'âge atteint au 31 décembre de la seconde année de la saison, comme dans
 * le football français), puis décalage éventuel décidé par le club
 * (surclassement = +1, sous-classement = -1). Si le club fonctionne
 * différemment, il suffit d'ajuster les constantes / calculer() ci-dessous.
 */
final class CategorieAge
{
    public const AGE_MIN = 6;
    public const AGE_SENIOR = 20;
    public const SENIOR = 'Senior';

    public static function fromAge(int $age): string
    {
        if ($age >= self::AGE_SENIOR) {
            return self::SENIOR;
        }

        return 'U' . max($age, self::AGE_MIN);
    }

    public static function calculer(\DateTimeInterface $naissance, ?Saison $saison = null, int $decalage = 0): string
    {
        return self::fromAge(self::anneeSaisonFin($saison) - (int) $naissance->format('Y') + $decalage);
    }

    public static function anneeSaisonFin(?Saison $saison = null): int
    {
        if ($saison?->getDateFin()) {
            return (int) $saison->getDateFin()->format('Y');
        }

        $now = new \DateTimeImmutable();

        return (int) $now->format('Y') + ((int) $now->format('n') >= 7 ? 1 : 0);
    }

    /** @return array<string, int> libellé => décalage, pour surclasser / sous-classer un licencié */
    public static function decalageChoices(): array
    {
        return [
            'Catégorie de son âge'        => 0,
            'Surclassé (+1 catégorie)'    => 1,
            'Surclassé (+2 catégories)'   => 2,
            'Sous-classé (−1 catégorie)'  => -1,
            'Sous-classé (−2 catégories)' => -2,
        ];
    }

    /** @return array<string, string> libellé => code, pour les listes déroulantes */
    public static function choices(): array
    {
        $choices = [];
        for ($age = self::AGE_MIN; $age < self::AGE_SENIOR; ++$age) {
            $choices['U' . $age] = 'U' . $age;
        }
        $choices[self::SENIOR] = self::SENIOR;

        return $choices;
    }
}

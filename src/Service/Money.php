<?php

namespace App\Service;

/** Affichage des montants (stockés en centimes) : 2 999 → « 29,99 € ». */
final class Money
{
    public static function format(int $centimes): string
    {
        // Espace insécable avant le symbole, séparateur de milliers = espace fine insécable.
        return number_format($centimes / 100, 2, ',', "\u{202F}") . "\u{00A0}€";
    }
}

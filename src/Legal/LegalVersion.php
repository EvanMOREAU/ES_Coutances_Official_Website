<?php

namespace App\Legal;

/**
 * Version en vigueur des textes légaux (CGV, politique de confidentialité). À changer à chaque
 * modification de fond : elle est enregistrée avec chaque acceptation (commande, création de
 * compte) pour pouvoir prouver quel texte a été accepté, et quand.
 */
final class LegalVersion
{
    public const CURRENT = '2026-10-08';

    private function __construct()
    {
    }

    /** Date de la version courante, pour l'affichage (« Dernière mise à jour : … »). */
    public static function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::CURRENT);
    }
}

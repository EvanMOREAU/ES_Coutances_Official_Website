<?php

namespace App\Translation;

/**
 * Champ texte saisi en français dans l'administration : à l'enregistrement de l'entité, il est traduit dans chaque
 * langue active et le résultat est gardé en base (voir TranslationStore). Le site n'appelle jamais le service de
 * traduction à l'affichage. « html » : le champ contient du HTML (les balises sont conservées).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Translatable
{
    public function __construct(public readonly bool $html = false)
    {
    }
}

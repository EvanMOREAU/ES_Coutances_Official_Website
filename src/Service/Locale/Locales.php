<?php

namespace App\Service\Locale;

/** Langues proposées par l'application. Une langue ajoutée ici exige son fichier translations/messages.<code>.yaml. */
final class Locales
{
    public const DEFAULT = 'fr';

    /** Cookie mémorisant la langue d'un visiteur non connecté (et celle du dernier compte connecté). */
    public const COOKIE = 'escoutances_locale';

    /**
     * Catalogue des langues : code => [nom dans sa propre langue, locale ICU pour les dates, sens d'écriture, drapeau
     * (public/images/flags), code cible DeepL]. Ce sont les langues qu'on peut activer ; celles réellement proposées sont filtrées par
     * LocaleRegistry (réglage administrateur).
     */
    public const AVAILABLE = [
        'fr' => ['name' => 'Français', 'icu' => 'fr_FR', 'dir' => 'ltr', 'flag' => 'fr.svg', 'deepl' => 'FR'],
        'en' => ['name' => 'English', 'icu' => 'en_GB', 'dir' => 'ltr', 'flag' => 'gb.svg', 'deepl' => 'EN-GB'],
        'de' => ['name' => 'Deutsch', 'icu' => 'de_DE', 'dir' => 'ltr', 'flag' => 'de.svg', 'deepl' => 'DE'],
        'ar' => ['name' => 'العربية', 'icu' => 'ar_MA', 'dir' => 'rtl', 'flag' => 'ar.svg', 'deepl' => 'AR'],
        'es' => ['name' => 'Español', 'icu' => 'es_ES', 'dir' => 'ltr', 'flag' => 'es.svg', 'deepl' => 'ES'],
        'pt' => ['name' => 'Português', 'icu' => 'pt_PT', 'dir' => 'ltr', 'flag' => 'pt.svg', 'deepl' => 'PT-PT'],
        'tr' => ['name' => 'Türkçe', 'icu' => 'tr_TR', 'dir' => 'ltr', 'flag' => 'tr.svg', 'deepl' => 'TR'],
    ];

    public static function isSupported(?string $locale): bool
    {
        return null !== $locale && isset(self::AVAILABLE[$locale]);
    }

    /** Code de langue valide, ou la langue par défaut. */
    public static function normalize(?string $locale): string
    {
        return self::isSupported($locale) ? (string) $locale : self::DEFAULT;
    }

    /** Sens d'écriture (« ltr » ou « rtl ») de la langue. */
    public static function dir(?string $locale): string
    {
        return self::AVAILABLE[self::normalize($locale)]['dir'];
    }

    public static function icu(string $locale): string
    {
        return self::AVAILABLE[self::normalize($locale)]['icu'];
    }
}

<?php

namespace App\Translation;

/** Service de traduction automatique (français → autre langue). */
interface TranslationProvider
{
    public function isConfigured(): bool;

    /**
     * @param list<string> $texts   textes français (en HTML si $html)
     * @param string       $locale  code de langue du site (en, de, ar…)
     *
     * @return list<string> traductions, dans le même ordre
     *
     * @throws \RuntimeException si le service est indisponible ou refuse la requête
     */
    public function translate(array $texts, string $locale, bool $html): array;
}

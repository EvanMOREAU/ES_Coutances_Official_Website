<?php

namespace App\Service\Locale;

use App\Repository\LocaleSettingRepository;

/**
 * Langues réellement proposées : celles du catalogue Locales::AVAILABLE que les administrateurs ont activées.
 * Le français (langue par défaut) est toujours actif ; une langue sans réglage en base reste active.
 */
final class LocaleRegistry
{
    /** @var array<string, bool>|null */
    private ?array $settings = null;

    public function __construct(private readonly LocaleSettingRepository $repository)
    {
    }

    public function isEnabled(?string $locale): bool
    {
        if (!Locales::isSupported($locale)) {
            return false;
        }
        if (Locales::DEFAULT === $locale) {
            return true;
        }

        return $this->settings()[$locale] ?? true;
    }

    /** @return array<string, array{name: string, icu: string, dir: string, flag: string, deepl: string}> */
    public function enabled(): array
    {
        return array_filter(Locales::AVAILABLE, fn (array $_, string $code): bool => $this->isEnabled($code), ARRAY_FILTER_USE_BOTH);
    }

    /** Code valide ET actif, sinon la langue par défaut. */
    public function normalize(?string $locale): string
    {
        return $this->isEnabled($locale) ? (string) $locale : Locales::DEFAULT;
    }

    /** @return array<string, bool> */
    private function settings(): array
    {
        return $this->settings ??= $this->repository->enabledByCode();
    }
}

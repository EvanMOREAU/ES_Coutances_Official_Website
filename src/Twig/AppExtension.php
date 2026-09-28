<?php

namespace App\Twig;

use App\Service\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('match_live', [AppRuntime::class, 'getMatchLive']),
            new TwigFunction('boutique_en_maintenance', [AppRuntime::class, 'isBoutiqueEnMaintenance']),
        ];
    }

    public function getFilters(): array
    {
        return [
            // {{ date|date_fr }} => « 20 février 2026 » ; le motif est celui d'IntlDateFormatter.
            new TwigFilter('date_fr', $this->formatDate(...)),
            // {{ 2999|money }} => « 29,99 € » (montants stockés en centimes).
            new TwigFilter('money', Money::format(...)),
        ];
    }

    public function formatDate(?\DateTimeInterface $date, string $pattern = 'd MMMM y'): string
    {
        if ($date === null) {
            return '';
        }

        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), \IntlDateFormatter::GREGORIAN, $pattern);

        return (string) $formatter->format($date);
    }
}

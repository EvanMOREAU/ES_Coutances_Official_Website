<?php

namespace App\Twig;

use App\Service\Locale\Locales;
use App\Service\Money;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private readonly ?RequestStack $requestStack = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('match_live', [AppRuntime::class, 'getMatchLive']),
            new TwigFunction('boutique_en_maintenance', [AppRuntime::class, 'isBoutiqueEnMaintenance']),
            // {% for code, lang in app_locales() %} : langues activées (code => [name, icu, dir, flag]).
            new TwigFunction('app_locales', [AppRuntime::class, 'getLocales']),
            // dir="{{ locale_dir(app.request.locale) }}" : sens d'écriture (ltr / rtl) de la langue.
            new TwigFunction('locale_dir', Locales::dir(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            // {{ date|date_fr }} => « 20 février 2026 » (ou « 20 February 2026 » selon la langue) ; le motif est celui d'IntlDateFormatter.
            new TwigFilter('date_fr', $this->formatDate(...)),
            // {{ 2999|money }} => « 29,99 € » (montants stockés en centimes).
            new TwigFilter('money', Money::format(...)),
            // {{ ('uploads/slides/'~nom)|thumb('hero') }} => adresse d'une miniature WebP mise en cache.
            // {{ offre.titre|auto_trans }} : texte saisi en français, affiché dans la langue du visiteur (traduction gardée en base).
            new TwigFilter('auto_trans', [AppRuntime::class, 'autoTrans']),
            new TwigFilter('thumb', [AppRuntime::class, 'thumb']),
            // {{ page.contenu|safe_html }} => HTML riche (Trix) assaini par liste blanche ; déjà sûr, pas de |raw.
            new TwigFilter('safe_html', [AppRuntime::class, 'safeHtml'], ['is_safe' => ['html']]),
        ];
    }

    public function formatDate(?\DateTimeInterface $date, string $pattern = 'd MMMM y'): string
    {
        if ($date === null) {
            return '';
        }

        $formatter = new \IntlDateFormatter(Locales::icu($this->requestStack?->getCurrentRequest()?->getLocale() ?? Locales::DEFAULT), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), \IntlDateFormatter::GREGORIAN, $pattern);

        return (string) $formatter->format($date);
    }
}

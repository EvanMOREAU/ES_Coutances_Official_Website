<?php

namespace App\Twig;

use App\Entity\MatchLive;
use App\Repository\BoutiqueSettingsRepository;
use App\Repository\MatchLiveRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Fournit aux templates (notamment base.html.twig, commun à toutes les pages)
 * des données globales qui ne dépendent d'aucun contrôleur en particulier.
 */
class AppRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MatchLiveRepository $matchLiveRepository,
        private readonly BoutiqueSettingsRepository $boutiqueSettingsRepository,
        private readonly CacheManager $imagine,
        #[Autowire('%kernel.project_dir%/public')] private readonly string $publicDir,
    ) {
    }

    public function getMatchLive(): ?MatchLive
    {
        return $this->matchLiveRepository->getSingleton();
    }

    public function isBoutiqueEnMaintenance(): bool
    {
        return $this->boutiqueSettingsRepository->getSingleton()?->isEnMaintenance() ?? false;
    }

    /**
     * Version allégée (redimensionnée, WebP) d'une image envoyée, d'après un filtre de config/packages/liip_imagine.yaml.
     * Les formats que GD ne sait pas retailler proprement (SVG, GIF animé…) et les fichiers absents sont servis tels quels.
     */
    public function thumb(string $path, string $filter): string
    {
        $relative = ltrim($path, '/');
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true) || !is_file($this->publicDir.'/'.$relative)) {
            return '/'.$relative;
        }

        return $this->imagine->getBrowserPath($relative, $filter);
    }
}

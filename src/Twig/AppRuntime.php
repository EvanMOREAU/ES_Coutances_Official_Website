<?php

namespace App\Twig;

use App\Entity\MatchLive;
use App\Repository\MatchLiveRepository;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Fournit aux templates (notamment base.html.twig, commun à toutes les pages)
 * des données globales qui ne dépendent d'aucun contrôleur en particulier.
 */
class AppRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MatchLiveRepository $matchLiveRepository,
    ) {
    }

    public function getMatchLive(): ?MatchLive
    {
        return $this->matchLiveRepository->getSingleton();
    }
}

<?php

namespace App\Twig;

use App\Service\Boutique\Panier;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BoutiqueExtension extends AbstractExtension
{
    public function __construct(private readonly Panier $panier)
    {
    }

    public function getFunctions(): array
    {
        return [
            // Nombre d'articles dans le panier du visiteur (pastille du menu du site).
            new TwigFunction('panier_count', $this->panier->count(...)),
        ];
    }
}

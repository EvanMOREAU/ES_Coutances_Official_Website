<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\FamilleRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PortailExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly FamilleRepository $familles,
    ) {
    }

    public function getFunctions(): array
    {
        // Factures et commandes : titulaire d'une famille (parent, ou adulte autonome), pas un mineur rattaché à un parent.
        return [new TwigFunction('portail_facturation', $this->hasBilling(...))];
    }

    public function hasBilling(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && null !== $this->familles->findOneBy(['user' => $user]);
    }
}

<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PortailExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly FamilleRepository $familles,
        private readonly LicencieRepository $licencies,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // Factures des licences : titulaire d'une famille (parent, ou adulte autonome), pas un mineur rattaché à un parent.
            new TwigFunction('portail_facturation', $this->hasBilling(...)),
            // Commandes boutique : familles + comptes boutique (sans famille ni licencié), mais pas un mineur rattaché à un parent.
            new TwigFunction('portail_commandes_visible', $this->hasCommandes(...)),
            // Compte boutique : créé librement, sans famille ni licencié rattaché (voir RegistrationController).
            new TwigFunction('portail_est_boutique', $this->estCompteBoutique(...)),
        ];
    }

    public function hasBilling(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && null !== $this->familles->findOneBy(['user' => $user]);
    }

    public function hasCommandes(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return null !== $this->familles->findOneBy(['user' => $user]) || null === $this->licencies->findOneBy(['user' => $user]);
    }

    public function estCompteBoutique(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User
            && null === $this->familles->findOneBy(['user' => $user])
            && null === $this->licencies->findOneBy(['user' => $user]);
    }
}

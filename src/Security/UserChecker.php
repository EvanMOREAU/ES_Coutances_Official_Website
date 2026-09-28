<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion des comptes de l'espace familles / licenciés dont plus
 * aucune licence n'est en cours (saison terminée ou licencié inactif). Les
 * comptes de gestion du club (éditeur, admin, dev) ne sont jamais concernés.
 */
class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly LicencieRepository $licencies,
        private readonly FamilleRepository $familles,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User || $user->isStaff()) {
            return;
        }

        $licencies = $this->licencies->findBy(['user' => $user]);
        if ($famille = $this->familles->findOneBy(['user' => $user])) {
            foreach ($famille->getLicencies() as $licencie) {
                $licencies[] = $licencie;
            }
        }

        // Un compte sans aucun licencié rattaché (famille en cours de création) reste utilisable.
        if ([] === $licencies) {
            return;
        }

        foreach ($licencies as $licencie) {
            if ($licencie->isEnCours()) {
                return;
            }
        }

        throw new CustomUserMessageAccountStatusException('Votre licence n\'est plus valide pour la saison en cours : ce compte est désactivé. Contactez le club pour la renouveler.');
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}

<?php

namespace App\Security;

use App\Entity\Licencie;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
class LicencieVoter extends Voter
{
    public const VIEW = 'LICENCIE_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof Licencie;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var Licencie $licencie */
        $licencie = $subject;

        // Le licencié voit sa propre fiche, ou la famille qui le porte voit sa fiche.
        return $licencie->getUser() === $currentUser
            || $licencie->getFamille()?->getUser() === $currentUser;
    }
}

<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class UserVoter extends Voter
{
    const DELETE = 'USER_DELETE';
    const EDIT   = 'USER_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::DELETE, self::EDIT])
            && $subject instanceof User;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Si la cible est un dev, seul un dev peut agir dessus
        if (in_array('ROLE_DEV', $targetUser->getRoles(), true)) {
            return in_array('ROLE_DEV', $currentUser->getRoles(), true);
        }

        // Sinon, admin ou dev peuvent agir
        return in_array('ROLE_ADMIN', $currentUser->getRoles(), true)
            || in_array('ROLE_DEV', $currentUser->getRoles(), true);
    }
}
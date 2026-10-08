<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** Mémorise la dernière connexion de chaque compte (durée de conservation des comptes inactifs, RGPD). */
final class LastLoginListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Requête directe : pas d'écouteur d'audit déclenché, pas de modification de updatedAt.
        $this->em->createQuery('UPDATE '.User::class.' u SET u.lastLoginAt = :now WHERE u.id = :id')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $user->getId())
            ->execute();
    }
}

<?php

namespace App\Repository;

use App\Entity\NotificationState;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationState> */
class NotificationStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationState::class);
    }

    /** @return array<string, NotificationState> états de l'utilisateur, indexés par clé */
    public function indexedFor(User $user): array
    {
        $states = [];
        foreach ($this->findBy(['user' => $user]) as $state) {
            $states[$state->getCle()] = $state;
        }

        return $states;
    }
}

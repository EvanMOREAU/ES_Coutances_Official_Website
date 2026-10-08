<?php

namespace App\Repository;

use App\Entity\MatchLive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<MatchLive>
 */
class MatchLiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchLive::class);
    }

    public function getSingleton(): ?MatchLive
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.id', SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

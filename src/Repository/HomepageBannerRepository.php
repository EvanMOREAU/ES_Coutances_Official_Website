<?php

namespace App\Repository;

use App\Entity\HomepageBanner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<HomepageBanner>
 */
class HomepageBannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageBanner::class);
    }

    public function getSingleton(): ?HomepageBanner
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.id', SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

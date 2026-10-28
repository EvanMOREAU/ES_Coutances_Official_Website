<?php

namespace App\Repository;

use App\Doctrine\SingletonCacheInvalidator;
use App\Entity\BoutiqueSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<BoutiqueSettings> */
class BoutiqueSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoutiqueSettings::class);
    }

    public function getSingleton(): ?BoutiqueSettings
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.id', SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->enableResultCache(SingletonCacheInvalidator::CACHE_TTL, SingletonCacheInvalidator::cacheKey(BoutiqueSettings::class))
            ->getOneOrNullResult();
    }
}

<?php

namespace App\Repository;

use App\Doctrine\SingletonCacheInvalidator;
use App\Entity\ContactSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<ContactSettings>
 */
class ContactSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactSettings::class);
    }

    public function getSingleton(): ?ContactSettings
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->enableResultCache(SingletonCacheInvalidator::CACHE_TTL, SingletonCacheInvalidator::cacheKey(ContactSettings::class))
            ->getOneOrNullResult();
    }
}

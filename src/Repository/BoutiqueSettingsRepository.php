<?php

namespace App\Repository;

use App\Entity\BoutiqueSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

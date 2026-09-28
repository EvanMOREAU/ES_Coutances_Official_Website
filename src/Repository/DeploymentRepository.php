<?php

namespace App\Repository;

use App\Entity\Deployment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deployment>
 */
class DeploymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deployment::class);
    }

    /** @return Deployment[] */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRunning(): ?Deployment
    {
        return $this->findOneBy(['status' => Deployment::STATUS_RUNNING], ['id' => 'DESC']);
    }
}

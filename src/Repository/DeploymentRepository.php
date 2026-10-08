<?php

namespace App\Repository;

use App\Entity\Deployment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Deployment>
 */
class DeploymentRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deployment::class);
    }

    /** @return Deployment[] */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.id', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique complet, paginé, du plus récent au plus ancien.
     *
     * @return array{rows: list<Deployment>, total: int}
     */
    public function paginated(int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $qb = $this->createQueryBuilder('d');

        $total = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $rows  = $qb->orderBy('d.id', SortDirection::Descending)
            ->setFirstResult(max(0, $page - 1) * $perPage)->setMaxResults($perPage)
            ->getQuery()->getResult();

        return ['rows' => $rows, 'total' => $total];
    }

    public function findRunning(): ?Deployment
    {
        return $this->findOneBy(['status' => Deployment::STATUS_RUNNING], ['id' => 'DESC']);
    }
}

<?php

namespace App\Repository;

use App\Entity\Licencie;
use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Licencie>
 */
class LicencieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Licencie::class);
    }

    public function countActifsBySaison(Saison $saison): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.saison = :saison')
            ->andWhere('l.actif = true')
            ->setParameter('saison', $saison)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

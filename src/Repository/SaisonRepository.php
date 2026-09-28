<?php

namespace App\Repository;

use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Saison>
 */
class SaisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saison::class);
    }

    public function findActive(): ?Saison
    {
        return $this->findOneBy(['active' => true]);
    }

    /**
     * Retourne la saison chronologiquement précédente (date de début la plus
     * proche avant celle de la saison donnée), utilisée pour calculer une
     * évolution d'une saison à l'autre.
     */
    public function findPrevious(Saison $saison): ?Saison
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.dateDebut < :dateDebut')
            ->setParameter('dateDebut', $saison->getDateDebut())
            ->orderBy('s.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

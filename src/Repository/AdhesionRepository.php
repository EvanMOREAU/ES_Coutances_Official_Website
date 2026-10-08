<?php

namespace App\Repository;

use App\Entity\Adhesion;
use App\Entity\Famille;
use App\Entity\Licencie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<Adhesion> */
class AdhesionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adhesion::class);
    }

    /** @return list<Adhesion> toutes les adhésions, dernières saisons d'abord, avec règlements et aides */
    public function findAllDetailed(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('l', 's', 'r', 'ai')
            ->leftJoin('a.licencie', 'l')->join('a.saison', 's')
            ->leftJoin('a.reglements', 'r')->leftJoin('a.aides', 'ai')
            ->orderBy('s.dateDebut', SortDirection::Descending)->addOrderBy('a.licencieLabel', SortDirection::Ascending)->addOrderBy('l.nom', SortDirection::Ascending)
            ->getQuery()->getResult();
    }

    /** @return list<Adhesion> historique d'un licencié, saison la plus récente d'abord */
    public function forLicencie(Licencie $licencie): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.saison', 's')
            ->andWhere('a.licencie = :l')->setParameter('l', $licencie)
            ->orderBy('s.dateDebut', SortDirection::Descending)
            ->getQuery()->getResult();
    }

    /** @return list<Adhesion> adhésions des licenciés d'une famille (pour l'espace parent) */
    public function forFamille(Famille $famille): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('l', 's')
            ->join('a.licencie', 'l')->join('a.saison', 's')
            ->andWhere('l.famille = :f')->setParameter('f', $famille)
            ->orderBy('s.dateDebut', SortDirection::Descending)->addOrderBy('l.prenom', SortDirection::Ascending)
            ->getQuery()->getResult();
    }
}

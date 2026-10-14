<?php

namespace App\Repository;

use App\Entity\ArticleVariante;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleVariante> */
class ArticleVarianteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleVariante::class);
    }

    /**
     * Tailles des articles actifs dont le stock est au plus égal au seuil (les plus basses d'abord).
     *
     * @return list<ArticleVariante>
     */
    public function findStockBas(int $seuil, int $limit = 50): array
    {
        if ($seuil <= 0) {
            return [];
        }

        return $this->createQueryBuilder('v')
            ->addSelect('a')
            ->join('v.article', 'a')
            ->andWhere('a.statut = :actif')
            ->andWhere('v.stock <= :seuil')
            ->setParameter('actif', 'active')
            ->setParameter('seuil', $seuil)
            ->orderBy('v.stock', 'ASC')
            ->addOrderBy('a.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Article> */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * @return list<Article> articles visibles dans la boutique, les plus récents d'abord
     *                        ($categorieSlug filtre sur une catégorie si fourni)
     */
    public function findVisibles(?string $categorieSlug = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('v')
            ->leftJoin('a.variantes', 'v')
            ->andWhere('a.statut = :actif')
            ->setParameter('actif', Article::STATUT_ACTIVE)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (null !== $categorieSlug) {
            $qb->join('a.categorie', 'c')
                ->andWhere('c.slug = :slug')
                ->setParameter('slug', $categorieSlug);
        }

        return $qb->getQuery()->getResult();
    }

    public function findVisibleBySlug(string $slug): ?Article
    {
        return $this->createQueryBuilder('a')
            ->addSelect('v')
            ->leftJoin('a.variantes', 'v')
            ->andWhere('a.slug = :slug AND a.statut = :actif')
            ->setParameter('slug', $slug)
            ->setParameter('actif', Article::STATUT_ACTIVE)
            ->getQuery()->getOneOrNullResult();
    }

    /** Slug unique à partir du nom de l'article (suffixe -2, -3… en cas de doublon). */
    public function uniqueSlug(Article $article): string
    {
        $base = $article->slugBase();
        $slug = $base;
        $i    = 2;
        while (($existing = $this->findOneBy(['slug' => $slug])) && $existing !== $article) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}

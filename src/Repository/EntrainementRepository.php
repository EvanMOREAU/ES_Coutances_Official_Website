<?php

namespace App\Repository;

use App\Entity\Entrainement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Entrainement> */
class EntrainementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entrainement::class);
    }

    /**
     * Séances entre deux dates (incluses), triées chronologiquement. Si
     * $categories est fourni, ne garde que celles ouvertes à l'une d'elles.
     *
     * @param list<string>|null $categories
     * @return list<Entrainement>
     */
    public function between(\DateTimeInterface $from, \DateTimeInterface $to, ?array $categories = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.date >= :from AND e.date <= :to')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('e.date', 'ASC')
            ->addOrderBy('e.heureDebut', 'ASC');

        if (null === $categories && null !== $limit) {
            $qb->setMaxResults($limit);
        }

        $result = $qb->getQuery()->getResult();

        // Filtrage par catégorie en PHP (colonne JSON) : le volume d'une période reste petit.
        if (null !== $categories) {
            $result = array_values(array_filter(
                $result,
                static fn (Entrainement $e) => [] !== array_intersect($e->getCategories(), $categories),
            ));
            if (null !== $limit) {
                $result = array_slice($result, 0, $limit);
            }
        }

        return $result;
    }

    /** @return list<Entrainement> séances d'une série à partir d'une date (incluse) */
    public function inSerieFrom(string $serie, \DateTimeInterface $from): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.serie = :serie AND e.date >= :from')
            ->setParameter('serie', $serie)
            ->setParameter('from', $from->format('Y-m-d'))
            ->orderBy('e.date', 'ASC')
            ->getQuery()->getResult();
    }
}

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

    /**
     * Évolution du nombre de licenciés actifs, mois par mois, sur les $months derniers mois (le
     * mois courant inclus). Mêmes règles que PartenaireRepository::monthlyEvolution().
     *
     * @return list<array{month: \DateTimeImmutable, new: int, total: int}>
     */
    public function monthlyEvolution(int $months = 12): array
    {
        $start = (new \DateTimeImmutable('first day of this month midnight'))->modify(sprintf('-%d months', $months - 1));

        $createdAts = $this->createQueryBuilder('l')
            ->select('l.createdAt')
            ->andWhere('l.actif = true')
            ->getQuery()
            ->getSingleColumnResult();

        $total = 0;
        $newByMonth = [];
        foreach ($createdAts as $createdAt) {
            $createdAt = null === $createdAt ? null : new \DateTimeImmutable($createdAt);
            if (null === $createdAt || $createdAt < $start) {
                ++$total;
                continue;
            }
            $key = $createdAt->format('Y-m');
            $newByMonth[$key] = ($newByMonth[$key] ?? 0) + 1;
        }

        $series = [];
        for ($i = 0; $i < $months; ++$i) {
            $month = $start->modify(sprintf('+%d months', $i));
            $new = $newByMonth[$month->format('Y-m')] ?? 0;
            $total += $new;
            $series[] = ['month' => $month, 'new' => $new, 'total' => $total];
        }

        return $series;
    }
}

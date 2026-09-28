<?php

namespace App\Repository;

use App\Entity\Partenaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Partenaire>
 */
class PartenaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partenaire::class);
    }

    /**
     * Évolution du nombre de partenaires actifs, mois par mois, sur les
     * $months derniers mois (le mois courant inclus).
     *
     * Les partenaires créés avant l'introduction de la date de création n'ont
     * pas de date : ils sont comptés dans la base de départ, pas dans un mois.
     *
     * @return list<array{month: \DateTimeImmutable, new: int, total: int}>
     */
    public function monthlyEvolution(int $months = 12): array
    {
        $start = (new \DateTimeImmutable('first day of this month midnight'))->modify(sprintf('-%d months', $months - 1));

        $createdAts = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Partenaire::STATUT_ACTIVE)
            ->getQuery()
            ->getSingleColumnResult();

        $total = 0;
        $newByMonth = [];
        foreach ($createdAts as $createdAt) {
            // Une sélection de colonne unique renvoie la date brute (chaîne), pas un objet.
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

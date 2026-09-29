<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Commande> */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /** Référence lisible et unique : ESC-260925-A3F9. */
    public function nouvelleReference(): string
    {
        do {
            $reference = sprintf('ESC-%s-%s', date('ymd'), strtoupper(bin2hex(random_bytes(2))));
        } while ($this->findOneBy(['reference' => $reference]));

        return $reference;
    }

    /** Commandes à traiter par le club (à préparer, ou prêtes mais pas encore retirées). */
    public function countAPreparer(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut = :s')
            ->setParameter('s', Commande::STATUT_NOUVELLE)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<Commande> */
    public function findAPreparer(int $limit = 5): array
    {
        return $this->findBy(['statut' => Commande::STATUT_NOUVELLE], ['id' => 'DESC'], $limit);
    }

    /** @return list<Commande> commandes d'un compte connecté, les plus récentes d'abord */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Chiffre d'affaires cumulé, mois par mois, sur les $months derniers mois (le mois courant
     * inclus), pour les commandes réellement payées (regroupées par date de paiement). Montants en
     * euros (pas en centimes), pour affichage direct par line_chart_controller.js. Mêmes règles que
     * PartenaireRepository::monthlyEvolution().
     *
     * @return list<array{month: \DateTimeImmutable, new: float, total: float}>
     */
    public function monthlyRevenue(int $months = 12): array
    {
        $start = (new \DateTimeImmutable('first day of this month midnight'))->modify(sprintf('-%d months', $months - 1));

        $rows = $this->createQueryBuilder('c')
            ->select('c.payeeLe AS payeeLe', 'c.createdAt AS createdAt', 'c.totalCentimes AS totalCentimes')
            ->andWhere('c.reglement = :paye')
            ->setParameter('paye', Commande::REGLEMENT_PAYE)
            ->getQuery()
            ->getArrayResult();

        $total = 0.0;
        $newByMonth = [];
        foreach ($rows as $row) {
            // getArrayResult() hydrate les colonnes de type date en objets DateTimeImmutable (à la
            // différence de getSingleColumnResult(), qui renvoie la valeur brute en chaîne).
            $date = $row['payeeLe'] ?? $row['createdAt'];
            $montant = $row['totalCentimes'] / 100;
            if ($date < $start) {
                $total += $montant;
                continue;
            }
            $key = $date->format('Y-m');
            $newByMonth[$key] = ($newByMonth[$key] ?? 0.0) + $montant;
        }

        $series = [];
        for ($i = 0; $i < $months; ++$i) {
            $month = $start->modify(sprintf('+%d months', $i));
            $new = round($newByMonth[$month->format('Y-m')] ?? 0.0, 2);
            $total = round($total + $new, 2);
            $series[] = ['month' => $month, 'new' => $new, 'total' => $total];
        }

        return $series;
    }
}

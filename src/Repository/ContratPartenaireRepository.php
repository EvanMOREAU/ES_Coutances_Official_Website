<?php

namespace App\Repository;

use App\Entity\ContratPartenaire;
use App\Entity\ContratPartenaireTache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<ContratPartenaire>
 */
class ContratPartenaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContratPartenaire::class);
    }

    /** Somme des montants de tous les contrats (centimes). */
    public function totalMontantCentimes(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.montantCentimes), 0)')
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Contrats avec un solde restant à encaisser (impayé ou partiel), le plus
     * proche de son échéance de fin en premier, paginés (le reste à encaisser
     * étant calculé en PHP, le filtrage et la pagination le sont aussi).
     *
     * @return array{rows: list<ContratPartenaire>, total: int, totalResteCentimes: int}
     */
    public function findAvecResteAPayer(int $page = 1, int $perPage = 10): array
    {
        $contrats = $this->createQueryBuilder('c')
            ->orderBy('c.dateFin', SortDirection::Ascending)
            ->getQuery()->getResult();

        $avecReste = array_values(array_filter($contrats, static fn (ContratPartenaire $c) => $c->getResteCentimes() > 0));

        return [
            'rows'               => array_slice($avecReste, max(0, $page - 1) * $perPage, $perPage),
            'total'              => count($avecReste),
            'totalResteCentimes' => array_sum(array_map(static fn (ContratPartenaire $c) => $c->getResteCentimes(), $avecReste)),
        ];
    }

    /**
     * Tâches non réalisées, tous contrats confondus, la plus proche échéance d'abord.
     *
     * @return array{rows: list<ContratPartenaireTache>, total: int}
     */
    public function tachesEnAttente(int $page = 1, int $perPage = 10): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('t')->from(ContratPartenaireTache::class, 't')
            ->andWhere('t.fait = false');

        $total = (int) (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        $rows  = $qb->orderBy('t.echeance', SortDirection::Ascending)
            ->setFirstResult(max(0, $page - 1) * $perPage)->setMaxResults($perPage)
            ->getQuery()->getResult();

        return ['rows' => $rows, 'total' => $total];
    }
}

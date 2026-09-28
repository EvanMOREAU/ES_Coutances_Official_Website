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
}

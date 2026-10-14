<?php

namespace App\Repository;

use App\Entity\Famille;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Famille>
 */
class FamilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Famille::class);
    }

    /** Nombre de familles dont le compte n'a pas d'adresse e-mail exploitable (une seule colonne lue, pas d'entités). */
    public function countSansEmailValide(): int
    {
        $emails = $this->createQueryBuilder('f')
            ->select('u.email')
            ->leftJoin('f.user', 'u')
            ->getQuery()
            ->getSingleColumnResult();

        $count = 0;
        foreach ($emails as $email) {
            if (!$email || !filter_var($email, \FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower((string) $email), '@import.local')) {
                ++$count;
            }
        }

        return $count;
    }
}

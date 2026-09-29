<?php

namespace App\Repository;

use App\Entity\CodePromo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CodePromo> */
class CodePromoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CodePromo::class);
    }

    /** Recherche insensible à la casse et aux espaces, tels que saisis par un client. */
    public function findParCode(string $code): ?CodePromo
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }
}

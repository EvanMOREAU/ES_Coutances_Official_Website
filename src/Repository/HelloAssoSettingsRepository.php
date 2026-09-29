<?php

namespace App\Repository;

use App\Entity\HelloAssoSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HelloAssoSettings> */
class HelloAssoSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HelloAssoSettings::class);
    }

    /** Réglages existants (ou une fiche vide, non enregistrée). */
    public function getSingleton(): HelloAssoSettings
    {
        return $this->findOneBy([]) ?? new HelloAssoSettings();
    }
}

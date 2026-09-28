<?php

namespace App\Repository;

use App\Entity\MailSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MailSettings> */
class MailSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailSettings::class);
    }

    /** Réglages existants (ou une fiche vide, non enregistrée). */
    public function getSingleton(): MailSettings
    {
        return $this->findOneBy([]) ?? new MailSettings();
    }
}

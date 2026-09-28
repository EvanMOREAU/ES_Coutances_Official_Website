<?php

namespace App\Service;

use App\Entity\Licencie;
use App\Repository\LicencieRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fin de saison : les licenciés rattachés à une saison terminée passent
 * « archivés » (inactifs). Leurs comptes ne peuvent plus se connecter (voir
 * UserChecker). Un nouvel import Foot Club réactive ceux dont la licence est
 * renouvelée en les rattachant à la saison en cours.
 */
class SaisonCloture
{
    public function __construct(
        private readonly LicencieRepository $licencies,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return int nombre de licenciés archivés */
    public function cloturer(): int
    {
        $termines = $this->licencies->createQueryBuilder('l')
            ->join('l.saison', 's')
            ->andWhere('s.dateFin < :today')
            ->andWhere('l.statut = :actif')
            ->setParameter('today', (new \DateTimeImmutable('today'))->format('Y-m-d'))
            ->setParameter('actif', Licencie::STATUT_ACTIVE)
            ->getQuery()->getResult();

        foreach ($termines as $licencie) {
            $licencie->setStatut(Licencie::STATUT_ARCHIVED);
        }
        $this->em->flush();

        return count($termines);
    }
}

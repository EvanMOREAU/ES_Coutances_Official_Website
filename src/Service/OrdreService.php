<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class OrdreService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Retourne le prochain ordre disponible pour une entité donnée.
     * Si l'ordre souhaité est déjà pris, décale tous les suivants de +1.
     */
    public function getNextOrdre(string $entityClass): int
    {
        $repo = $this->em->getRepository($entityClass);

        $result = $repo->createQueryBuilder('e')
            ->select('MAX(e.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    public function ensureUniqueOrdre(string $entityClass, object $entity): void
    {
        $repo = $this->em->getRepository($entityClass);
        $ordre = $entity->getOrdre();
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;

        // Cherche si un autre élément a déjà cet ordre
        $qb = $repo->createQueryBuilder('e')
            ->where('e.ordre >= :ordre')
            ->setParameter('ordre', $ordre)
            ->orderBy('e.ordre', 'ASC');

        // Exclut l'entité en cours de modification
        if ($id) {
            $qb->andWhere('e.id != :id')->setParameter('id', $id);
        }

        $conflicts = $qb->getQuery()->getResult();

        if (empty($conflicts)) {
            return; // Pas de conflit, rien à faire
        }

        // Décale tous les conflits de +1
        foreach ($conflicts as $conflict) {
            $conflict->setOrdre($conflict->getOrdre() + 1);
            $this->em->persist($conflict);
        }
    }
}
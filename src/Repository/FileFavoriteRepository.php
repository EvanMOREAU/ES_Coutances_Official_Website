<?php

namespace App\Repository;

use App\Entity\FileFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FileFavorite>
 */
class FileFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileFavorite::class);
    }

    /** @return list<string> chemins virtuels favoris de l'utilisateur */
    public function pathsFor(User $user): array
    {
        return array_map(
            static fn (array $row) => $row['path'],
            $this->createQueryBuilder('f')->select('f.path')->where('f.user = :u')->setParameter('u', $user)->getQuery()->getArrayResult(),
        );
    }

    /** Un fichier ou dossier disparaît : ses favoris (et ceux de son contenu) aussi. */
    public function forget(string $path): void
    {
        $this->createQueryBuilder('f')
            ->delete()
            ->where('f.path = :p OR f.path LIKE :prefix')
            ->setParameter('p', $path)
            ->setParameter('prefix', addcslashes($path, '%_\\').'/%')
            ->getQuery()
            ->execute();
    }

    /** Un fichier ou dossier est renommé : ses favoris suivent. */
    public function move(string $from, string $to): void
    {
        foreach ($this->createQueryBuilder('f')
            ->where('f.path = :p OR f.path LIKE :prefix')
            ->setParameter('p', $from)
            ->setParameter('prefix', addcslashes($from, '%_\\').'/%')
            ->getQuery()->getResult() as $favorite) {
            $favorite->setPath($to.substr($favorite->getPath(), strlen($from)));
        }
        $this->getEntityManager()->flush();
    }
}

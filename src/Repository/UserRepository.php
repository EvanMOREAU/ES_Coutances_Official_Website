<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Clients de la boutique : les comptes limités à la boutique (ni compte famille ou licencié, ni rattaché à
     * une fiche famille / licencié), plus les comptes famille ou licencié qui ont déjà commandé. L'équipe du club
     * (encadrant, admin, dev) n'en fait jamais partie.
     *
     * @return list<User>
     */
    public function findBoutiqueClients(): array
    {
        return $this->boutiqueClientsQuery()->getQuery()->getResult();
    }

    /** Le compte est-il limité à la boutique (voir findBoutiqueClients) ? */
    public function isBoutiqueClient(User $user): bool
    {
        return null !== $this->boutiqueClientsQuery()->andWhere('u = :me')->setParameter('me', $user)->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    private function boutiqueClientsQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->andWhere("u.roles NOT LIKE '%ROLE_EDITOR%' AND u.roles NOT LIKE '%ROLE_ADMIN%' AND u.roles NOT LIKE '%ROLE_DEV%'")
            ->andWhere("(u.roles NOT LIKE '%ROLE_FAMILLE%' AND u.roles NOT LIKE '%ROLE_LICENCIE%'
                    AND NOT EXISTS (SELECT f.id FROM App\Entity\Famille f WHERE f.user = u)
                    AND NOT EXISTS (SELECT l.id FROM App\Entity\Licencie l WHERE l.user = u))
                OR EXISTS (SELECT c.id FROM App\Entity\Commande c WHERE c.user = u)")
            ->orderBy('u.nom', SortDirection::Ascending)
            ->addOrderBy('u.prenom', SortDirection::Ascending);
    }

    /** @return list<User> */
    public function findAllOrderedByRole(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy("CASE 
                WHEN u.roles LIKE '%ROLE_DEV%' THEN 0 
                WHEN u.roles LIKE '%ROLE_ADMIN%' THEN 1 
                WHEN u.roles LIKE '%ROLE_EDITOR%' THEN 2 
                ELSE 3 END", SortDirection::Ascending)
            ->addOrderBy('u.nom', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}

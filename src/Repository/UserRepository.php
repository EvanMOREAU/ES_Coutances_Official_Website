<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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

    public function findAllOrderedByRole(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy("CASE 
                WHEN u.roles LIKE '%ROLE_DEV%' THEN 0 
                WHEN u.roles LIKE '%ROLE_ADMIN%' THEN 1 
                WHEN u.roles LIKE '%ROLE_EDITOR%' THEN 2 
                ELSE 3 END", 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

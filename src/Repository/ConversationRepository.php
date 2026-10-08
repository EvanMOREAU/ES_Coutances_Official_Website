<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Discussions d'un utilisateur, la plus récente en premier (avec tous leurs participants).
     *
     * @return list<Conversation>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p', 'pu')
            ->innerJoin('c.participants', 'me', 'WITH', 'me.user = :u')
            ->innerJoin('c.participants', 'p')
            ->innerJoin('p.user', 'pu')
            ->setParameter('u', $user)
            ->orderBy('c.updatedAt', SortDirection::Descending)
            ->addOrderBy('c.id', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les discussions de support (visibles de l'équipe habilitée), avec leurs participants.
     *
     * @return list<Conversation>
     */
    public function findSupport(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p', 'pu', 'cu')
            ->leftJoin('c.participants', 'p')
            ->leftJoin('p.user', 'pu')
            ->leftJoin('c.customer', 'cu')
            ->where('c.support = true')
            ->orderBy('c.updatedAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function findSupportOf(User $customer): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->where('c.support = true')
            ->andWhere('c.customer = :u')
            ->setParameter('u', $customer)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Messages des clients restés sans lecture pour un membre de l'équipe qui n'a pas encore ouvert la discussion de support.
     *
     * @param list<int> $conversationIds
     *
     * @return array<int, int>
     */
    public function supportUnreadForStaff(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(m.conversation) AS conversation', 'COUNT(m.id) AS unread')
            ->from(\App\Entity\Message::class, 'm')
            ->innerJoin('m.conversation', 'c')
            ->where('IDENTITY(m.conversation) IN (:ids)')
            ->andWhere('m.author = c.customer')
            ->groupBy('m.conversation')
            ->setParameter('ids', $conversationIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['conversation']] = (int) $row['unread'];
        }

        return $counts;
    }

    /** Discussion à deux déjà ouverte entre ces deux personnes, s'il y en a une. */
    public function findDirect(User $a, User $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'pa', 'WITH', 'pa.user = :a')
            ->innerJoin('c.participants', 'pb', 'WITH', 'pb.user = :b')
            ->where('c.isGroup = false')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Nombre de messages non lus par discussion pour cet utilisateur (ceux des autres, après son dernier message lu).
     *
     * @param list<int> $conversationIds
     *
     * @return array<int, int>
     */
    public function unreadCounts(User $user, array $conversationIds = []): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(m.conversation) AS conversation', 'COUNT(m.id) AS unread')
            ->from(\App\Entity\Message::class, 'm')
            ->innerJoin(\App\Entity\ConversationParticipant::class, 'p', 'ON', 'p.conversation = m.conversation AND p.user = :u')
            ->where('m.id > p.lastReadMessageId')
            ->andWhere('(m.author IS NULL OR m.author <> :u)')
            ->groupBy('m.conversation')
            ->setParameter('u', $user);

        if ($conversationIds !== []) {
            $qb->andWhere('IDENTITY(m.conversation) IN (:ids)')->setParameter('ids', $conversationIds);
        }

        $counts = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $counts[(int) $row['conversation']] = (int) $row['unread'];
        }

        return $counts;
    }
}

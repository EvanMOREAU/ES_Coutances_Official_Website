<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Messages d'une discussion, du plus ancien au plus récent.
     * - $after : uniquement les messages plus récents que cet identifiant (mise à jour en direct) ;
     * - $before : uniquement les messages plus anciens (chargement de l'historique).
     *
     * @return list<Message>
     */
    public function page(Conversation $conversation, ?int $after = null, ?int $before = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('a')
            ->leftJoin('m.author', 'a')
            ->where('m.conversation = :c')
            ->setParameter('c', $conversation);

        if ($after !== null) {
            // Tout ce qui est arrivé depuis (borné, par prudence).
            return array_values($qb->andWhere('m.id > :after')->setParameter('after', $after)
                ->orderBy('m.id', 'ASC')->setMaxResults(200)->getQuery()->getResult());
        }

        if ($before !== null) {
            $qb->andWhere('m.id < :before')->setParameter('before', $before);
        }
        $messages = $qb->orderBy('m.id', 'DESC')->setMaxResults($limit)->getQuery()->getResult();

        return array_reverse($messages);
    }

    public function latestId(Conversation $conversation): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('MAX(m.id)')
            ->where('m.conversation = :c')
            ->setParameter('c', $conversation)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Dernier message de chacune des discussions données.
     *
     * @param list<int> $conversationIds
     *
     * @return array<int, Message> indexé par identifiant de discussion
     */
    public function lastOf(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $ids = $this->createQueryBuilder('m')
            ->select('MAX(m.id)')
            ->where('IDENTITY(m.conversation) IN (:ids)')
            ->groupBy('m.conversation')
            ->setParameter('ids', $conversationIds)
            ->getQuery()
            ->getSingleColumnResult();
        if ($ids === []) {
            return [];
        }

        $last = [];
        foreach ($this->createQueryBuilder('m')->addSelect('a')->leftJoin('m.author', 'a')->where('m.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult() as $message) {
            $last[$message->getConversation()->getId()] = $message;
        }

        return $last;
    }
}

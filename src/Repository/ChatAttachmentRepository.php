<?php

namespace App\Repository;

use App\Entity\ChatAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatAttachment>
 */
class ChatAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatAttachment::class);
    }

    /**
     * Documents joints à ces messages.
     *
     * @param list<int> $messageIds
     *
     * @return array<int, ChatAttachment> indexé par identifiant de message
     */
    public function byMessages(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $map = [];
        foreach ($this->createQueryBuilder('a')->where('IDENTITY(a.message) IN (:ids)')->setParameter('ids', $messageIds)->getQuery()->getResult() as $attachment) {
            $map[$attachment->getMessage()->getId()] = $attachment;
        }

        return $map;
    }

    /** @return list<ChatAttachment> documents arrivés à échéance et pas encore traités */
    public function expired(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.purgedAt IS NULL')
            ->andWhere('a.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}

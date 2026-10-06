<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Lien entre une discussion et l'un de ses participants, avec son état de lecture. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_conversation_user', columns: ['conversation_id', 'user_id'])]
class ConversationParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Identifiant du dernier message lu (les messages suivants comptent comme « non lus »). */
    #[ORM\Column]
    private int $lastReadMessageId = 0;

    /** Dernier e-mail « nouveau message » envoyé à ce participant (limite le nombre d'e-mails). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastNotifiedAt = null;

    public function getLastNotifiedAt(): ?\DateTimeImmutable { return $this->lastNotifiedAt; }
    public function markNotified(): static { $this->lastNotifiedAt = new \DateTimeImmutable(); return $this; }

    public function __construct(Conversation $conversation, User $user)
    {
        $this->conversation = $conversation;
        $this->user         = $user;
    }

    public function getId(): ?int { return $this->id; }
    public function getConversation(): Conversation { return $this->conversation; }
    public function getUser(): User { return $this->user; }

    public function getLastReadMessageId(): int { return $this->lastReadMessageId; }
    public function markReadUpTo(int $messageId): static
    {
        $this->lastReadMessageId = max($this->lastReadMessageId, $messageId);

        return $this;
    }
}

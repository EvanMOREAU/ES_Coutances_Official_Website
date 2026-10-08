<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Index(name: 'idx_message_conversation', columns: ['conversation_id', 'id'])]
class Message
{
    public const MAX_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    /** Auteur (null si son compte a été supprimé : l'historique est conservé). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Conversation $conversation, User $author, string $body)
    {
        $this->conversation = $conversation;
        $this->author       = $author;
        $this->body         = $body;
        $this->createdAt    = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getConversation(): Conversation
    {
        return $this->conversation;
    }
    public function getAuthor(): ?User
    {
        return $this->author;
    }
    public function getBody(): string
    {
        return $this->body;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

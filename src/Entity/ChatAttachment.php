<?php

namespace App\Entity;

use App\Repository\ChatAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Document joint à un message de la messagerie.
 *
 * Deux cas : un fichier envoyé depuis l'appareil (« temporaire » : il est stocké dans
 * l'espace de l'expéditeur et supprimé du disque à l'échéance), ou un document déjà
 * présent dans l'espace de l'expéditeur (on ne fait que le partager : à l'échéance
 * l'accès se ferme, le fichier d'origine reste). Dans les deux cas la ligne est
 * conservée : l'historique garde la trace qu'un document a été échangé.
 */
#[ORM\Entity(repositoryClass: ChatAttachmentRepository::class)]
#[ORM\Index(name: 'idx_chat_attachment_expiry', columns: ['expires_at', 'purged_at'])]
class ChatAttachment
{
    /** Durée de mise à disposition d'un document. */
    public const RETENTION = 'P14D';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $owner;

    /** Nom affiché (celui du fichier au moment de l'envoi). */
    #[ORM\Column(length: 255)]
    private string $name;

    /** Chemin virtuel du gestionnaire de fichiers (« documents/prenom-nom-12/… »). */
    #[ORM\Column(length: 500)]
    private string $path;

    #[ORM\Column]
    private int $size = 0;

    /** Vrai : le fichier est supprimé du disque à l'échéance. */
    #[ORM\Column]
    private bool $temporary;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    /** Date à laquelle le document a cessé d'être disponible (null tant qu'il l'est). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $purgedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Message $message, ?User $owner, string $name, string $path, int $size, bool $temporary)
    {
        $this->message   = $message;
        $this->owner     = $owner;
        $this->name      = $name;
        $this->path      = $path;
        $this->size      = $size;
        $this->temporary = $temporary;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->add(new \DateInterval(self::RETENTION));
    }

    public function getId(): ?int { return $this->id; }
    public function getMessage(): Message { return $this->message; }
    public function getOwner(): ?User { return $this->owner; }
    public function getName(): string { return $this->name; }
    public function getPath(): string { return $this->path; }
    public function getSize(): int { return $this->size; }
    public function isTemporary(): bool { return $this->temporary; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getPurgedAt(): ?\DateTimeImmutable { return $this->purgedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->purgedAt !== null || $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    public function markPurged(): void
    {
        $this->purgedAt ??= new \DateTimeImmutable();
    }
}

<?php

namespace App\Entity;

use App\Repository\ContactMessageRepository;
use Doctrine\ORM\Mapping as ORM;

/** Message envoyé par un visiteur depuis le formulaire de la page de contact (conservé en base, consultable dans l'admin). */
#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
#[ORM\Index(columns: ['created_at'])]
class ContactMessage
{
    /** Types de demande proposés par le formulaire, avec leur libellé. */
    public const TYPES = [
        'renseignement' => 'Renseignement général',
        'inscription'   => 'Inscription / Licence',
        'partenariat'   => 'Partenariat',
        'presse'        => 'Presse / Médias',
        'recrutement'   => 'Recrutement / Bénévolat',
        'autre'         => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $nom = '';

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 30)]
    private string $type = 'autre';

    #[ORM\Column(length: 255)]
    private string $sujet = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Date de première lecture dans l'admin (null = nouveau message). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    /** Message traité : il quitte la boîte de réception sans être supprimé. */
    #[ORM\Column(options: ['default' => false])]
    private bool $archived = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = mb_substr($nom, 0, 180);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_substr($email, 0, 180);

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $telephone       = null === $telephone ? '' : trim($telephone);
        $this->telephone = '' === $telephone ? null : mb_substr($telephone, 0, 50);

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = mb_substr($type, 0, 30);

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSujet(): string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = mb_substr($sujet, 0, 255);

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(): static
    {
        $this->readAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function markUnread(): static
    {
        $this->readAt = null;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }
}

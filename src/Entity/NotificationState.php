<?php

namespace App\Entity;

use App\Repository\NotificationStateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * État d'une notification pour un utilisateur : lue et/ou masquée. Les
 * notifications elles-mêmes sont calculées à la volée (voir
 * AdminNotificationProvider) ; seule leur clé stable est mémorisée ici.
 */
#[ORM\Entity(repositoryClass: NotificationStateRepository::class)]
#[ORM\Table(name: 'notification_state')]
#[ORM\UniqueConstraint(name: 'UNIQ_NOTIFICATION_STATE', columns: ['user_id', 'cle'])]
class NotificationState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 190)]
    private ?string $cle = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $luLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $masqueeLe = null;

    public function __construct(User $user, string $cle)
    {
        $this->user = $user;
        $this->cle  = $cle;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getCle(): ?string
    {
        return $this->cle;
    }

    public function isLue(): bool
    {
        return null !== $this->luLe;
    }
    public function isMasquee(): bool
    {
        return null !== $this->masqueeLe;
    }

    public function marquerLue(): static
    {
        $this->luLe ??= new \DateTimeImmutable();

        return $this;
    }

    public function masquer(): static
    {
        $this->luLe ??= new \DateTimeImmutable();
        $this->masqueeLe ??= new \DateTimeImmutable();

        return $this;
    }
}

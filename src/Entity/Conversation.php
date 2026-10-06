<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Discussion de la messagerie : entre deux personnes (ex. un joueur et un
 * gérant du club) ou de groupe (plusieurs personnes, avec un titre).
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Index(name: 'idx_conversation_updated', columns: ['updated_at'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Titre d'une discussion de groupe (vide pour une discussion à deux : on affiche le nom de l'interlocuteur). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column]
    private bool $isGroup = false;

    /** Discussion de support : un client face à « Support », que toute personne du club habilitée peut tenir. */
    #[ORM\Column(options: ['default' => false])]
    private bool $support = false;

    /** Le client d'une discussion de support. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $customer = null;

    /** Dernier e-mail envoyé à l'équipe du support pour cette discussion. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $staffNotifiedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Date du dernier message : sert à trier la liste des discussions. */
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ConversationParticipant> */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: ConversationParticipant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->createdAt    = new \DateTimeImmutable();
        $this->updatedAt    = $this->createdAt;
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $subject): static { $this->subject = $subject !== null && trim($subject) !== '' ? mb_substr(trim($subject), 0, 120) : null; return $this; }

    public function isGroup(): bool { return $this->isGroup; }
    public function setIsGroup(bool $isGroup): static { $this->isGroup = $isGroup; return $this; }

    public function getStaffNotifiedAt(): ?\DateTimeImmutable { return $this->staffNotifiedAt; }
    public function markStaffNotified(): static { $this->staffNotifiedAt = new \DateTimeImmutable(); return $this; }

    public function isSupport(): bool { return $this->support; }
    public function getCustomer(): ?User { return $this->customer; }
    public function markAsSupport(User $customer): static { $this->support = true; $this->customer = $customer; $this->isGroup = false; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /** @return Collection<int, ConversationParticipant> */
    public function getParticipants(): Collection { return $this->participants; }

    public function addParticipant(User $user): ConversationParticipant
    {
        $participant = new ConversationParticipant($this, $user);
        $this->participants->add($participant);

        return $participant;
    }

    public function participantFor(User $user): ?ConversationParticipant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser()->getId() === $user->getId()) {
                return $participant;
            }
        }

        return null;
    }
}

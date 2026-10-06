<?php

namespace App\Entity;

use App\Repository\MatchLiveRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchLiveRepository::class)]
class MatchLive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $enLigne = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    /** Plage horaire pendant laquelle le match est automatiquement « en direct ». */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $debutAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEnLigne(): bool
    {
        return $this->enLigne;
    }

    public function setEnLigne(bool $enLigne): static
    {
        $this->enLigne = $enLigne;

        return $this;
    }

    public function getDebutAt(): ?\DateTimeImmutable
    {
        return $this->debutAt;
    }

    public function setDebutAt(?\DateTimeImmutable $debutAt): static
    {
        $this->debutAt = $debutAt;

        return $this;
    }

    public function getFinAt(): ?\DateTimeImmutable
    {
        return $this->finAt;
    }

    public function setFinAt(?\DateTimeImmutable $finAt): static
    {
        $this->finAt = $finAt;

        return $this;
    }

    /** Une plage est-elle programmée (et pas encore terminée) ? */
    public function isProgramme(?\DateTimeImmutable $now = null): bool
    {
        return $this->debutAt !== null && $this->finAt !== null && $this->finAt > ($now ?? new \DateTimeImmutable());
    }

    /** Dans la plage horaire programmée à cet instant ? */
    public function isDansLaPlage(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->debutAt !== null && $this->finAt !== null && $this->debutAt <= $now && $now < $this->finAt;
    }

    /** Le site affiche-t-il le match comme étant en direct : activé à la main, ou dans la plage programmée. */
    public function isEnDirect(?\DateTimeImmutable $now = null): bool
    {
        return $this->enLigne || $this->isDansLaPlage($now);
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

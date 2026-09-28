<?php

namespace App\Entity;

use App\Repository\BoutiqueSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/** Réglages globaux de la boutique (singleton), notamment la mise en maintenance. */
#[ORM\Entity(repositoryClass: BoutiqueSettingsRepository::class)]
class BoutiqueSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $enMaintenance = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEnMaintenance(): bool
    {
        return $this->enMaintenance;
    }

    public function setEnMaintenance(bool $enMaintenance): static
    {
        $this->enMaintenance = $enMaintenance;

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

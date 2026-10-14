<?php

namespace App\Entity;

use App\Repository\BoutiqueSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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

    /** Une taille dont le stock est inférieur ou égal à ce seuil est signalée (liste des articles, cloche) ; 0 désactive l'alerte. */
    #[ORM\Column(options: ['default' => 3])]
    #[Assert\Range(min: 0, max: 1000, notInRangeMessage: 'Indiquez un nombre entre 0 et 1000.')]
    private int $seuilStockBas = 3;

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

    public function getSeuilStockBas(): int
    {
        return $this->seuilStockBas;
    }

    public function setSeuilStockBas(?int $seuilStockBas): static
    {
        $this->seuilStockBas = (int) $seuilStockBas;

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

<?php

namespace App\Entity;

use App\Repository\ChiffresClesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChiffresClesRepository::class)]
class ChiffresCles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $nbLicencies = 0;

    #[ORM\Column]
    private int $nbEducateurs = 0;

    #[ORM\Column]
    private int $nbBenevoles = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNbLicencies(): int
    {
        return $this->nbLicencies;
    }

    public function setNbLicencies(int $nbLicencies): static
    {
        $this->nbLicencies = $nbLicencies;

        return $this;
    }

    public function getNbEducateurs(): int
    {
        return $this->nbEducateurs;
    }

    public function setNbEducateurs(int $nbEducateurs): static
    {
        $this->nbEducateurs = $nbEducateurs;

        return $this;
    }

    public function getNbBenevoles(): int
    {
        return $this->nbBenevoles;
    }

    public function setNbBenevoles(int $nbBenevoles): static
    {
        $this->nbBenevoles = $nbBenevoles;

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

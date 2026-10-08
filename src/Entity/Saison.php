<?php

namespace App\Entity;

use App\Entity\Concern\CreatedAtTrait;
use App\Entity\Concern\StatutTrait;
use App\Repository\SaisonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaisonRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Saison
{
    use CreatedAtTrait;
    use StatutTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Indiquez le libellé de la saison (ex. 2026-2027).')]
    #[Assert\Length(max: 20, maxMessage: 'Maximum 20 caractères.')]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la date de début.')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la date de fin.')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column]
    private bool $active = false;

    public function __toString(): string
    {
        return $this->libelle ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}

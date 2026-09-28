<?php

namespace App\Entity;

use App\Repository\EquipeRepository;
use App\Entity\Concern\CreatedAtTrait;
use App\Entity\Concern\StatutTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Équipe rattachée à une catégorie d'âge (ex. "U11 A", "U11 B"). Les licenciés
 * sont affiliés à une ou plusieurs équipes pour les matchs.
 */
#[ORM\Entity(repositoryClass: EquipeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Equipe
{
    use CreatedAtTrait;
    use StatutTrait { setStatut as private setStatutBase; }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 20)]
    private ?string $categorie = null;

    #[ORM\Column]
    private bool $actif = true;

    public function __toString(): string
    {
        return $this->nom ?? '';
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(string $categorie): static { $this->categorie = $categorie; return $this; }

    public function isActif(): bool { return $this->actif; }

    /** Vrai uniquement quand le statut est "actif" (utilisé par le site public). */
    public function setStatut(string $statut): static
    {
        $this->setStatutBase($statut);
        $this->actif = self::STATUT_ACTIVE === $statut;

        return $this;
    }

    public function setActif(bool $actif): static
    {
        if ($actif) {
            return $this->setStatut(self::STATUT_ACTIVE);
        }

        return $this->setStatut(self::STATUT_ACTIVE === $this->getStatut() ? self::STATUT_ARCHIVED : $this->getStatut());
    }
}

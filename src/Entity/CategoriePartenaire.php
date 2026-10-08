<?php

namespace App\Entity;

use App\Repository\CategoriePartenaireRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catégorie (rôle) d'un partenaire : gold, argent, fournisseur, institutionnel…
 * Définie par un développeur (voir templates/admin/_layout.html.twig, rubrique « Développeur »),
 * puis choisie sur chaque partenaire.
 */
#[ORM\Entity(repositoryClass: CategoriePartenaireRepository::class)]
class CategoriePartenaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Donnez un nom à la catégorie.')]
    #[Assert\Length(max: 100, maxMessage: 'Maximum 100 caractères.')]
    private ?string $nom = null;

    #[ORM\Column]
    private int $ordre = 0;

    public function __toString(): string
    {
        return $this->nom ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }
    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

/** Catégorie d'articles de la boutique (ex. T-shirts, Joggings). */
#[ORM\Entity(repositoryClass: CategorieArticleRepository::class)]
class CategorieArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Donnez un nom à la catégorie.')]
    private ?string $nom = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $ordre = 0;

    public function __toString(): string { return $this->nom ?? ''; }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): static { $this->nom = $nom; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    /** Base de slug ; l'unicité est garantie par CategorieArticleRepository::uniqueSlug(). */
    public function slugBase(): string
    {
        return (new AsciiSlugger('fr'))->slug((string) $this->nom)->lower()->toString() ?: 'categorie';
    }

    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }
}

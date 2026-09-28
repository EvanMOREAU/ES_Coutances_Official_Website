<?php

namespace App\Entity;

use App\Repository\ArticleVarianteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Déclinaison d'un article (une taille) et son stock. */
#[ORM\Entity(repositoryClass: ArticleVarianteRepository::class)]
class ArticleVariante
{
    public const LIBELLE_UNIQUE = 'Unique';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'variantes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Indiquez la taille (ex. M).')]
    private ?string $libelle = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stock = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $ordre = 0;

    public function getId(): ?int { return $this->id; }

    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): static { $this->article = $article; return $this; }

    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $libelle): static { $this->libelle = null === $libelle ? null : trim($libelle); return $this; }

    public function getStock(): int { return $this->stock; }
    public function setStock(?int $stock): static { $this->stock = max(0, (int) $stock); return $this; }

    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }

    public function __toString(): string { return (string) $this->libelle; }
}

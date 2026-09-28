<?php

namespace App\Entity;

use App\Entity\Concern\CreatedAtTrait;
use App\Repository\EntrainementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Séance du planning (entraînement) ouverte à une ou plusieurs catégories
 * d'âge (U11, U12…). Les séances répétées chaque semaine partagent le même
 * identifiant de série, ce qui permet de les modifier / supprimer ensemble.
 */
#[ORM\Entity(repositoryClass: EntrainementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'IDX_ENTRAINEMENT_DATE', columns: ['date'])]
class Entrainement
{
    use CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public const TYPE_ENTRAINEMENT = 'entrainement';
    public const TYPE_RENCONTRE    = 'rencontre';

    /** « entrainement » (une ou plusieurs catégories) ou « rencontre » (entre deux équipes du club). */
    #[ORM\Column(length: 20, options: ['default' => 'entrainement'])]
    private string $type = self::TYPE_ENTRAINEMENT;

    /**
     * Entraînement : équipes visées à l'intérieur des catégories (vide = toute la catégorie).
     * Rencontre : les deux équipes qui s'affrontent.
     *
     * @var list<int>
     */
    #[ORM\Column(type: 'json')]
    private array $equipes = [];

    #[ORM\Column(length: 150)]
    private string $titre = 'Entraînement';

    /** @var list<string> codes de catégorie (U6…U19, Senior) */
    #[ORM\Column(type: 'json')]
    private array $categories = [];

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: 'time_immutable')]
    private ?\DateTimeImmutable $heureDebut = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureFin = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $serie = null;

    public function getId(): ?int { return $this->id; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function isRencontre(): bool { return self::TYPE_RENCONTRE === $this->type; }

    /** @return list<int> */
    public function getEquipes(): array { return $this->equipes; }
    /** @param list<int> $equipes */
    public function setEquipes(array $equipes): static { $this->equipes = array_values(array_unique(array_map('intval', $equipes))); return $this; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    /** @return list<string> */
    public function getCategories(): array { return $this->categories; }
    /** @param list<string> $categories */
    public function setCategories(array $categories): static { $this->categories = array_values(array_unique($categories)); return $this; }

    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }

    public function getHeureDebut(): ?\DateTimeImmutable { return $this->heureDebut; }
    public function setHeureDebut(\DateTimeImmutable $heureDebut): static { $this->heureDebut = $heureDebut; return $this; }

    public function getHeureFin(): ?\DateTimeImmutable { return $this->heureFin; }
    public function setHeureFin(?\DateTimeImmutable $heureFin): static { $this->heureFin = $heureFin; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getSerie(): ?string { return $this->serie; }
    public function setSerie(?string $serie): static { $this->serie = $serie; return $this; }
}

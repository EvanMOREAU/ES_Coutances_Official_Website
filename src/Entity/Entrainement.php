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
    public const TYPE_EVENEMENT    = 'evenement';

    /** « entrainement », « rencontre » (entre deux équipes du club) ou « evenement » (interne, réservé aux coachs/administrateurs). */
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

    /**
     * Événement interne uniquement : à qui il est partagé. Vide des deux côtés = tout le
     * monde (tous les comptes ayant accès au planning) ; sinon, seuls les comptes dont le
     * profil d'autorisation ou l'un des rôles correspond le voient.
     *
     * @var list<int> identifiants de ProfilAutorisation
     */
    #[ORM\Column(type: 'json')]
    private array $partageProfils = [];

    /** @var list<string> rôles (ROLE_DEV, ROLE_ADMIN, ROLE_EDITOR…) */
    #[ORM\Column(type: 'json')]
    private array $partageRoles = [];

    /**
     * Événement interne uniquement : comptes précis auxquels il est partagé.
     *
     * @var list<int> identifiants de User
     */
    #[ORM\Column(type: 'json')]
    private array $partageUtilisateurs = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
    public function isRencontre(): bool
    {
        return self::TYPE_RENCONTRE === $this->type;
    }
    public function isEvenement(): bool
    {
        return self::TYPE_EVENEMENT === $this->type;
    }

    /** @return list<int> */
    public function getPartageProfils(): array
    {
        return $this->partageProfils;
    }
    /** @param list<int> $ids */
    public function setPartageProfils(array $ids): static
    {
        $this->partageProfils = array_values(array_unique(array_map('intval', $ids)));

        return $this;
    }

    /** @return list<string> */
    public function getPartageRoles(): array
    {
        return $this->partageRoles;
    }
    /** @param list<string> $roles */
    public function setPartageRoles(array $roles): static
    {
        $this->partageRoles = array_values(array_unique(array_map('strval', $roles)));

        return $this;
    }

    /** @return list<int> */
    public function getPartageUtilisateurs(): array
    {
        return $this->partageUtilisateurs;
    }
    /** @param list<int> $ids */
    public function setPartageUtilisateurs(array $ids): static
    {
        $this->partageUtilisateurs = array_values(array_unique(array_map('intval', $ids)));

        return $this;
    }

    /** Un événement sans restriction (profils, rôles et comptes vides) est partagé à tout le monde. */
    public function isPartageTous(): bool
    {
        return [] === $this->partageProfils && [] === $this->partageRoles && [] === $this->partageUtilisateurs;
    }

    /** @return list<int> */
    public function getEquipes(): array
    {
        return $this->equipes;
    }
    /** @param list<int> $equipes */
    public function setEquipes(array $equipes): static
    {
        $this->equipes = array_values(array_unique(array_map('intval', $equipes)));

        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }
    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    /** @return list<string> */
    public function getCategories(): array
    {
        return $this->categories;
    }
    /** @param list<string> $categories */
    public function setCategories(array $categories): static
    {
        $this->categories = array_values(array_unique($categories));

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }
    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHeureDebut(): ?\DateTimeImmutable
    {
        return $this->heureDebut;
    }
    public function setHeureDebut(\DateTimeImmutable $heureDebut): static
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): ?\DateTimeImmutable
    {
        return $this->heureFin;
    }
    public function setHeureFin(?\DateTimeImmutable $heureFin): static
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }
    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSerie(): ?string
    {
        return $this->serie;
    }
    public function setSerie(?string $serie): static
    {
        $this->serie = $serie;

        return $this;
    }
}

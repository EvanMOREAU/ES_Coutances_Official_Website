<?php

namespace App\Entity;

use App\Repository\CodePromoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bon de livraison : code saisi par le client au moment du paiement d'une commande boutique
 * pour renseigner une adresse et se faire livrer, au lieu de retirer sa commande au club.
 * Il n'y a pas de réduction associée : le code sert uniquement à débloquer la livraison.
 *
 * Attribution : soit réservé à un utilisateur précis (lui seul peut l'utiliser, utilisable
 * dès sa création), soit ouvert à tout le monde — mais dans ce cas il doit d'abord être
 * validé (approuve) par un compte autorisé avant de devenir utilisable.
 */
#[ORM\Entity(repositoryClass: CodePromoRepository::class)]
#[ORM\Table(name: 'code_promo')]
class CodePromo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    /** Nombre d'utilisations maximum, ou null si illimité. */
    #[ORM\Column(nullable: true)]
    private ?int $usageMax = null;

    #[ORM\Column]
    private int $usageActuel = 0;

    /** Réservé à cet utilisateur, ou null si ouvert à tout le monde. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $utilisateur = null;

    /** Un bon ouvert à tout le monde (utilisateur = null) doit être validé avant d'être utilisable. */
    #[ORM\Column]
    private bool $approuve = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

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

    public function isActif(): bool
    {
        return $this->actif;
    }
    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

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

    public function getUsageMax(): ?int
    {
        return $this->usageMax;
    }
    public function setUsageMax(?int $usageMax): static
    {
        $this->usageMax = $usageMax;

        return $this;
    }

    public function getUsageActuel(): int
    {
        return $this->usageActuel;
    }

    public function incrementerUsage(): static
    {
        ++$this->usageActuel;

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }
    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    /** Ouvert à tout le monde (pas réservé à un utilisateur précis) ? */
    public function isOuvertATous(): bool
    {
        return null === $this->utilisateur;
    }

    public function isApprouve(): bool
    {
        return $this->approuve;
    }
    public function setApprouve(bool $approuve): static
    {
        $this->approuve = $approuve;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Le code est-il utilisable maintenant (actif, dans sa période de validité, pas épuisé,
     * et — s'il est ouvert à tout le monde — validé) ? Ne vérifie pas à qui il est réservé :
     * voir estUtilisablePar() pour ça.
     */
    public function isValide(?\DateTimeImmutable $maintenant = null): bool
    {
        $maintenant ??= new \DateTimeImmutable();

        if (!$this->actif) {
            return false;
        }
        if ($this->dateDebut && $maintenant < $this->dateDebut) {
            return false;
        }
        if ($this->dateFin && $maintenant > $this->dateFin) {
            return false;
        }
        if (null !== $this->usageMax && $this->usageActuel >= $this->usageMax) {
            return false;
        }
        if ($this->isOuvertATous() && !$this->approuve) {
            return false; // ouvert à tous, mais pas encore validé
        }

        return true;
    }

    /** Ce compte (ou personne, en visiteur non connecté) peut-il utiliser ce code ? */
    public function estUtilisablePar(?User $utilisateur): bool
    {
        return $this->isOuvertATous() || $utilisateur?->getId() === $this->utilisateur->getId();
    }
}

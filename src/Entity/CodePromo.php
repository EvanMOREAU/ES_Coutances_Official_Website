<?php

namespace App\Entity;

use App\Repository\CodePromoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Code de réduction utilisable au moment du paiement d'une commande boutique.
 *
 * Un code « bon de livraison » (autoriseLivraison) a lui aussi une réduction
 * (souvent 0%) mais permet en plus au client de renseigner une adresse pour
 * que le club lui livre sa commande, au lieu de la faire retirer au club.
 */
#[ORM\Entity(repositoryClass: CodePromoRepository::class)]
#[ORM\Table(name: 'code_promo')]
class CodePromo
{
    public const TYPE_POURCENTAGE = 'pourcentage';
    public const TYPE_MONTANT     = 'montant';

    public const TYPES = [
        self::TYPE_POURCENTAGE => 'Pourcentage',
        self::TYPE_MONTANT     => 'Montant fixe',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_POURCENTAGE;

    /** Pourcentage (0-100) ou montant en centimes, selon le type. */
    #[ORM\Column]
    private int $valeur = 0;

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

    #[ORM\Column]
    private bool $autoriseLivraison = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = strtoupper(trim($code)); return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException(sprintf('Type de code promo inconnu "%s".', $type));
        }
        $this->type = $type;

        return $this;
    }
    public function getTypeLabel(): string { return self::TYPES[$this->type]; }

    public function getValeur(): int { return $this->valeur; }
    public function setValeur(int $valeur): static { $this->valeur = max(0, $valeur); return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(?\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getUsageMax(): ?int { return $this->usageMax; }
    public function setUsageMax(?int $usageMax): static { $this->usageMax = $usageMax; return $this; }

    public function getUsageActuel(): int { return $this->usageActuel; }

    public function incrementerUsage(): static { ++$this->usageActuel; return $this; }

    public function isAutoriseLivraison(): bool { return $this->autoriseLivraison; }
    public function setAutoriseLivraison(bool $autoriseLivraison): static { $this->autoriseLivraison = $autoriseLivraison; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /** Le code est-il utilisable maintenant (actif, dans sa période de validité, pas épuisé) ? */
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

        return true;
    }

    /** Réduction appliquée à un total donné, en centimes, jamais plus que ce total. */
    public function calculerReductionCentimes(int $totalCentimes): int
    {
        $reduction = self::TYPE_POURCENTAGE === $this->type
            ? intdiv($totalCentimes * $this->valeur, 100)
            : $this->valeur;

        return max(0, min($reduction, $totalCentimes));
    }
}

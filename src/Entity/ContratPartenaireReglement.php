<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Une échéance de règlement d'un contrat partenaire (virement, chèque, espèces…). */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'contrat_partenaire_reglement')]
class ContratPartenaireReglement
{
    public const MODE_VIREMENT = 'virement';
    public const MODE_CHEQUE   = 'cheque';
    public const MODE_ESPECES  = 'especes';
    public const MODE_CARTE    = 'carte';
    public const MODE_AUTRE    = 'autre';

    public const MODES = [
        self::MODE_VIREMENT => 'Virement',
        self::MODE_CHEQUE   => 'Chèque',
        self::MODE_ESPECES  => 'Espèces',
        self::MODE_CARTE    => 'Carte bancaire',
        self::MODE_AUTRE    => 'Autre',
    ];

    /** Un règlement encaissé (tel qu'enregistré en base) est définitif : plus aucun champ ne peut être modifié. */
    private bool $locked = false;

    #[ORM\PostLoad]
    public function lockIfValidated(): void
    {
        $this->locked = $this->recu;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ContratPartenaire::class, inversedBy: 'reglements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ContratPartenaire $contrat = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::MODE_VIREMENT, self::MODE_CHEQUE, self::MODE_ESPECES, self::MODE_CARTE, self::MODE_AUTRE], message: 'Choisissez un mode de règlement.')]
    private string $mode = self::MODE_VIREMENT;

    #[ORM\Column]
    #[Assert\Positive(message: 'Indiquez un montant.')]
    private int $montantCentimes = 0;

    /** Position dans l'échelonnement (1, 2, 3…). */
    #[ORM\Column(options: ['default' => 1])]
    private int $ordre = 1;

    /** Date à laquelle ce règlement doit être encaissé. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateEcheance = null;

    /** Règlement encaissé. */
    #[ORM\Column(options: ['default' => false])]
    private bool $recu = false;

    /** Date d'encaissement effective. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateRemise = null;

    /** Numéro de chèque, référence du virement… */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContrat(): ?ContratPartenaire
    {
        return $this->contrat;
    }
    public function setContrat(?ContratPartenaire $contrat): static
    {
        $this->contrat = $contrat;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
    public function setMode(string $mode): static
    {
        if ($this->locked) {
            return $this;
        } $this->mode = $mode;

        return $this;
    }
    public function getModeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    public function getMontantCentimes(): int
    {
        return $this->montantCentimes;
    }
    public function setMontantCentimes(?int $c): static
    {
        if ($this->locked) {
            return $this;
        } $this->montantCentimes = max(0, (int) $c);

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }
    public function setOrdre(int $ordre): static
    {
        if ($this->locked) {
            return $this;
        } $this->ordre = $ordre;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }
    public function setDateEcheance(?\DateTimeImmutable $d): static
    {
        if ($this->locked) {
            return $this;
        } $this->dateEcheance = $d;

        return $this;
    }

    public function isRecu(): bool
    {
        return $this->recu;
    }
    public function setRecu(bool $recu): static
    {
        if ($this->locked) {
            return $this;
        } $this->recu = $recu;

        return $this;
    }

    public function getDateRemise(): ?\DateTimeImmutable
    {
        return $this->dateRemise;
    }
    public function setDateRemise(?\DateTimeImmutable $d): static
    {
        if ($this->locked) {
            return $this;
        } $this->dateRemise = $d;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }
    public function setReference(?string $reference): static
    {
        if ($this->locked) {
            return $this;
        } $this->reference = $reference;

        return $this;
    }
}

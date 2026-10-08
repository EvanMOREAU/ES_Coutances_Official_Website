<?php

namespace App\Entity;

use App\Repository\AdhesionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Licence d'un licencié pour une saison, et le suivi de son paiement : montant dû,
 * règlements (espèces, carte, chèques, en 1 à 3 fois, avec date de remise en banque)
 * et aides (Pass'Sport, Spot 50…) dont l'encaissement peut arriver des mois plus tard.
 * Une adhésion par saison : l'historique des saisons précédentes est conservé.
 */
#[ORM\Entity(repositoryClass: AdhesionRepository::class)]
#[ORM\Table(name: 'adhesion')]
#[ORM\UniqueConstraint(name: 'UNIQ_ADHESION_LICENCIE_SAISON', columns: ['licencie_id', 'saison_id'])]
class Adhesion
{
    public const PAIEMENT_IMPAYE       = 'impaye';
    public const PAIEMENT_PARTIEL      = 'partiel';
    public const PAIEMENT_ATTENTE_AIDE = 'attente_aides';
    public const PAIEMENT_PAYE         = 'paye';

    public const PAIEMENTS = [
        self::PAIEMENT_IMPAYE       => 'Impayée',
        self::PAIEMENT_PARTIEL      => 'Partiellement payée',
        self::PAIEMENT_ATTENTE_AIDE => 'Aides en attente',
        self::PAIEMENT_PAYE         => 'Payée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Licencie::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Licencie $licencie = null;

    /**
     * Nom de la personne quand elle n'est pas (encore) enregistrée comme licencié : la licence
     * est alors créée pour un simple libellé, à rattacher plus tard à une fiche licencié.
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $licencieLabel = null;

    #[ORM\ManyToOne(targetEntity: Saison::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saison $saison = null;

    /** Prix de la licence avant réduction, en centimes. */
    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le montant ne peut pas être négatif.')]
    private int $montantBaseCentimes = 0;

    /** Réduction accordée (ex. famille nombreuse), en centimes. */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'La réduction ne peut pas être négative.')]
    private int $reductionCentimes = 0;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $reductionMotif = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, Reglement> */
    #[ORM\OneToMany(targetEntity: Reglement::class, mappedBy: 'adhesion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => SortDirection::Ascending, 'id' => SortDirection::Ascending])]
    #[Assert\Valid]
    private Collection $reglements;

    /** @var Collection<int, AideFinanciere> */
    #[ORM\OneToMany(targetEntity: AideFinanciere::class, mappedBy: 'adhesion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => SortDirection::Ascending])]
    #[Assert\Valid]
    private Collection $aides;

    public function __construct()
    {
        $this->reglements = new ArrayCollection();
        $this->aides      = new ArrayCollection();
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLicencie(): ?Licencie
    {
        return $this->licencie;
    }
    public function setLicencie(?Licencie $licencie): static
    {
        $this->licencie = $licencie;

        return $this;
    }

    public function getLicencieLabel(): ?string
    {
        return $this->licencieLabel;
    }
    public function setLicencieLabel(?string $label): static
    {
        $this->licencieLabel = $label;

        return $this;
    }

    /** Nom affiché, que la licence soit rattachée à un licencié ou à un simple libellé. */
    public function getLicencieAffichage(): string
    {
        if ($this->licencie) {
            return trim($this->licencie->getPrenom().' '.$this->licencie->getNom());
        }

        return $this->licencieLabel ?? '—';
    }

    public function getSaison(): ?Saison
    {
        return $this->saison;
    }
    public function setSaison(?Saison $saison): static
    {
        $this->saison = $saison;

        return $this;
    }

    public function getMontantBaseCentimes(): int
    {
        return $this->montantBaseCentimes;
    }
    public function setMontantBaseCentimes(?int $c): static
    {
        $this->montantBaseCentimes = max(0, (int) $c);

        return $this;
    }

    public function getReductionCentimes(): int
    {
        return $this->reductionCentimes;
    }
    public function setReductionCentimes(?int $c): static
    {
        $this->reductionCentimes = max(0, (int) $c);

        return $this;
    }

    public function getReductionMotif(): ?string
    {
        return $this->reductionMotif;
    }
    public function setReductionMotif(?string $motif): static
    {
        $this->reductionMotif = $motif;

        return $this;
    }

    public function getObservation(): ?string
    {
        return $this->observation;
    }
    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Reglement> */
    public function getReglements(): Collection
    {
        return $this->reglements;
    }

    public function addReglement(Reglement $r): static
    {
        if (!$this->reglements->contains($r)) {
            $this->reglements->add($r);
            $r->setAdhesion($this);
        }

        return $this;
    }

    public function removeReglement(Reglement $r): static
    {
        if (!$r->isLocked()) {
            $this->reglements->removeElement($r);
        }

        return $this;
    }

    /** @return Collection<int, AideFinanciere> */
    public function getAides(): Collection
    {
        return $this->aides;
    }

    public function addAide(AideFinanciere $a): static
    {
        if (!$this->aides->contains($a)) {
            $this->aides->add($a);
            $a->setAdhesion($this);
        }

        return $this;
    }

    public function removeAide(AideFinanciere $a): static
    {
        if (!$a->isLocked()) {
            $this->aides->removeElement($a);
        }

        return $this;
    }

    // --- Montants (tous en centimes) -----------------------------------------

    /** Montant à payer une fois la réduction déduite. */
    public function getMontantDuCentimes(): int
    {
        return max(0, $this->montantBaseCentimes - $this->reductionCentimes);
    }

    public function getReglementsPrevusCentimes(): int
    {
        return array_sum($this->reglements->map(static fn (Reglement $r) => $r->getMontantCentimes())->toArray());
    }

    public function getReglementsRecusCentimes(): int
    {
        return array_sum($this->reglements->filter(static fn (Reglement $r) => $r->isRecu())->map(static fn (Reglement $r) => $r->getMontantCentimes())->toArray());
    }

    public function getAidesPrevuesCentimes(): int
    {
        return array_sum($this->aides->map(static fn (AideFinanciere $a) => $a->getMontantCentimes())->toArray());
    }

    public function getAidesRecuesCentimes(): int
    {
        return array_sum($this->aides->filter(static fn (AideFinanciere $a) => $a->isRecue())->map(static fn (AideFinanciere $a) => $a->getMontantCentimes())->toArray());
    }

    public function getAidesAttenduesCentimes(): int
    {
        return $this->getAidesPrevuesCentimes() - $this->getAidesRecuesCentimes();
    }

    /** Total réellement encaissé (règlements reçus + aides reçues). */
    public function getRecuCentimes(): int
    {
        return $this->getReglementsRecusCentimes() + $this->getAidesRecuesCentimes();
    }

    /** Reste à encaisser, toutes sources confondues. */
    public function getResteCentimes(): int
    {
        return max(0, $this->getMontantDuCentimes() - $this->getRecuCentimes());
    }

    /** Ce que le licencié / la famille doit encore régler lui-même (hors aides prévues). */
    public function getResteFamilleCentimes(): int
    {
        return max(0, $this->getMontantDuCentimes() - $this->getAidesPrevuesCentimes() - $this->getReglementsRecusCentimes());
    }

    public function getStatutPaiement(): string
    {
        if (0 === $this->getMontantDuCentimes() || $this->getResteCentimes() <= 0) {
            return self::PAIEMENT_PAYE;
        }
        if ($this->getAidesAttenduesCentimes() > 0 && $this->getResteFamilleCentimes() <= 0) {
            return self::PAIEMENT_ATTENTE_AIDE;
        }

        return $this->getRecuCentimes() > 0 ? self::PAIEMENT_PARTIEL : self::PAIEMENT_IMPAYE;
    }

    public function getStatutPaiementLabel(): string
    {
        return self::PAIEMENTS[$this->getStatutPaiement()];
    }

    /**
     * Le total prévu (aides + règlements, encaissés ou non) doit couvrir exactement le montant
     * dû : ni manquant, ni excédentaire, pour que le plan de paiement corresponde au prix réel.
     */
    #[Assert\Callback]
    public function validateBudget(ExecutionContextInterface $context): void
    {
        $du = $this->getMontantDuCentimes();
        if (0 === $du) {
            return;
        }

        $prevu = $this->getAidesPrevuesCentimes() + $this->getReglementsPrevusCentimes();
        $ecartCentimes = $du - $prevu;
        if (0 === $ecartCentimes) {
            return;
        }

        $montant = number_format(abs($ecartCentimes) / 100, 2, ',', ' ');
        $message = $ecartCentimes > 0
            ? sprintf('Il manque %s € pour couvrir le prix de la licence : complétez les aides ou les règlements.', $montant)
            : sprintf('Le total des aides et des règlements dépasse le prix de la licence de %s €.', $montant);

        $context->buildViolation($message)->atPath('reglements')->addViolation();
    }
}

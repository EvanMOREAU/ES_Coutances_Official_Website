<?php

namespace App\Entity;

use App\Repository\ContratPartenaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contrat d'un partenaire ou sponsor : montant, période, règlements échelonnés (comme les
 * licences), tâches à réaliser (flocage, pose d'un panneau…) et documents hébergés (contrat
 * signé…). Un partenaire garde l'historique de ses contrats précédents.
 */
#[ORM\Entity(repositoryClass: ContratPartenaireRepository::class)]
#[ORM\Table(name: 'contrat_partenaire')]
class ContratPartenaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Partenaire::class, inversedBy: 'contrats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Partenaire $partenaire = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Donnez un intitulé au contrat.')]
    private ?string $titre = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le montant ne peut pas être négatif.')]
    private int $montantCentimes = 0;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, ContratPartenaireReglement> */
    #[ORM\OneToMany(targetEntity: ContratPartenaireReglement::class, mappedBy: 'contrat', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $reglements;

    /** @var Collection<int, ContratPartenaireTache> */
    #[ORM\OneToMany(targetEntity: ContratPartenaireTache::class, mappedBy: 'contrat', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $taches;

    /** @var Collection<int, ContratPartenaireDocument> */
    #[ORM\OneToMany(targetEntity: ContratPartenaireDocument::class, mappedBy: 'contrat', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $documents;

    public function __construct()
    {
        $this->reglements = new ArrayCollection();
        $this->taches      = new ArrayCollection();
        $this->documents   = new ArrayCollection();
        $this->createdAt   = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPartenaire(): ?Partenaire { return $this->partenaire; }
    public function setPartenaire(?Partenaire $partenaire): static { $this->partenaire = $partenaire; return $this; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $titre): static { $this->titre = $titre; return $this; }

    public function getMontantCentimes(): int { return $this->montantCentimes; }
    public function setMontantCentimes(?int $c): static { $this->montantCentimes = max(0, (int) $c); return $this; }

    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(?\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, ContratPartenaireReglement> */
    public function getReglements(): Collection { return $this->reglements; }

    public function addReglement(ContratPartenaireReglement $r): static
    {
        if (!$this->reglements->contains($r)) {
            $this->reglements->add($r);
            $r->setContrat($this);
        }

        return $this;
    }

    public function removeReglement(ContratPartenaireReglement $r): static { $this->reglements->removeElement($r); return $this; }

    /** @return Collection<int, ContratPartenaireTache> */
    public function getTaches(): Collection { return $this->taches; }

    public function addTache(ContratPartenaireTache $t): static
    {
        if (!$this->taches->contains($t)) {
            $this->taches->add($t);
            $t->setContrat($this);
        }

        return $this;
    }

    public function removeTache(ContratPartenaireTache $t): static { $this->taches->removeElement($t); return $this; }

    /** @return Collection<int, ContratPartenaireDocument> */
    public function getDocuments(): Collection { return $this->documents; }

    public function addDocument(ContratPartenaireDocument $d): static
    {
        if (!$this->documents->contains($d)) {
            $this->documents->add($d);
            $d->setContrat($this);
        }

        return $this;
    }

    public function removeDocument(ContratPartenaireDocument $d): static { $this->documents->removeElement($d); return $this; }

    // --- Montants (en centimes) et statut de paiement -------------------------

    public function getReglementsRecusCentimes(): int
    {
        return array_sum($this->reglements->filter(static fn (ContratPartenaireReglement $r) => $r->isRecu())->map(static fn (ContratPartenaireReglement $r) => $r->getMontantCentimes())->toArray());
    }

    public function getResteCentimes(): int { return max(0, $this->montantCentimes - $this->getReglementsRecusCentimes()); }

    public const PAIEMENT_IMPAYE  = 'impaye';
    public const PAIEMENT_PARTIEL = 'partiel';
    public const PAIEMENT_PAYE    = 'paye';

    public const PAIEMENTS = [
        self::PAIEMENT_IMPAYE  => 'Impayé',
        self::PAIEMENT_PARTIEL => 'Partiellement payé',
        self::PAIEMENT_PAYE    => 'Payé',
    ];

    public function getStatutPaiement(): string
    {
        if (0 === $this->montantCentimes || $this->getResteCentimes() <= 0) {
            return self::PAIEMENT_PAYE;
        }

        return $this->getReglementsRecusCentimes() > 0 ? self::PAIEMENT_PARTIEL : self::PAIEMENT_IMPAYE;
    }

    public function getStatutPaiementLabel(): string { return self::PAIEMENTS[$this->getStatutPaiement()]; }

    // --- Tâches -----------------------------------------------------------------

    public function getTachesTotal(): int { return $this->taches->count(); }

    public function getTachesRestantes(): int
    {
        return $this->taches->filter(static fn (ContratPartenaireTache $t) => !$t->isFait())->count();
    }
}

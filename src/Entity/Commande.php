<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Commande de la boutique, à retirer au club (pas de livraison). Le règlement
 * se fait par carte (en ligne), espèces ou chèque (au club) : dans ces deux
 * derniers cas le paiement reste « en attente » jusqu'à l'encaissement.
 */
#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
#[ORM\Index(name: 'IDX_COMMANDE_STATUT', columns: ['statut'])]
class Commande
{
    // Avancement de la commande
    public const STATUT_NOUVELLE = 'nouvelle';   // à préparer
    public const STATUT_PRETE    = 'prete';      // prête à être retirée au club
    public const STATUT_RETIREE  = 'retiree';    // remise au client
    public const STATUT_ANNULEE  = 'annulee';

    public const STATUTS = [
        self::STATUT_NOUVELLE => 'À préparer',
        self::STATUT_PRETE    => 'Prête à retirer',
        self::STATUT_RETIREE  => 'Retirée',
        self::STATUT_ANNULEE  => 'Annulée',
    ];

    // Modes de règlement
    public const PAIEMENT_CARTE   = 'carte';
    public const PAIEMENT_ESPECES = 'especes';
    public const PAIEMENT_CHEQUE  = 'cheque';

    public const MODES_PAIEMENT = [
        self::PAIEMENT_CARTE   => 'Carte bancaire',
        self::PAIEMENT_ESPECES => 'Espèces au club',
        self::PAIEMENT_CHEQUE  => 'Chèque au club',
    ];

    // État du règlement
    public const REGLEMENT_ATTENTE = 'en_attente';
    public const REGLEMENT_PAYE    = 'paye';

    public const REGLEMENTS = [
        self::REGLEMENT_ATTENTE => 'En attente',
        self::REGLEMENT_PAYE    => 'Payée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $reference = null;

    /** Jeton secret : donne accès à la page de suivi sans compte. */
    #[ORM\Column(length: 40, unique: true)]
    private ?string $token = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_NOUVELLE;

    #[ORM\Column(length: 20)]
    private string $modePaiement = self::PAIEMENT_ESPECES;

    #[ORM\Column(length: 20)]
    private string $reglement = self::REGLEMENT_ATTENTE;

    #[ORM\Column]
    private int $totalCentimes = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $payeeLe = null;

    /** @var Collection<int, CommandeLigne> */
    #[ORM\OneToMany(targetEntity: CommandeLigne::class, mappedBy: 'commande', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes    = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->token     = bin2hex(random_bytes(20));
    }

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }

    public function getToken(): ?string { return $this->token; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getNomComplet(): string { return trim($this->prenom . ' ' . $this->nom); }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static
    {
        if (!isset(self::STATUTS[$statut])) {
            throw new \InvalidArgumentException(sprintf('Statut de commande inconnu "%s".', $statut));
        }
        $this->statut = $statut;

        return $this;
    }
    public function getStatutLabel(): string { return self::STATUTS[$this->statut]; }

    public function getModePaiement(): string { return $this->modePaiement; }
    public function setModePaiement(string $modePaiement): static
    {
        if (!isset(self::MODES_PAIEMENT[$modePaiement])) {
            throw new \InvalidArgumentException(sprintf('Mode de paiement inconnu "%s".', $modePaiement));
        }
        $this->modePaiement = $modePaiement;

        return $this;
    }
    public function getModePaiementLabel(): string { return self::MODES_PAIEMENT[$this->modePaiement]; }

    public function getReglement(): string { return $this->reglement; }
    public function getReglementLabel(): string { return self::REGLEMENTS[$this->reglement]; }
    public function isPayee(): bool { return self::REGLEMENT_PAYE === $this->reglement; }

    public function marquerPayee(): static
    {
        $this->reglement = self::REGLEMENT_PAYE;
        $this->payeeLe ??= new \DateTimeImmutable();

        return $this;
    }

    public function getPayeeLe(): ?\DateTimeImmutable { return $this->payeeLe; }

    public function getTotalCentimes(): int { return $this->totalCentimes; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, CommandeLigne> */
    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(CommandeLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
            $this->recalculer();
        }

        return $this;
    }

    public function getNombreArticles(): int
    {
        return array_sum($this->lignes->map(static fn (CommandeLigne $l) => $l->getQuantite())->toArray());
    }

    public function isAnnulable(): bool
    {
        return in_array($this->statut, [self::STATUT_NOUVELLE, self::STATUT_PRETE], true);
    }

    /** Commande encore en cours (ni retirée ni annulée). */
    public function isEnCours(): bool { return $this->isAnnulable(); }

    /** Le client doit encore régler quelque chose. */
    public function isReglementDu(): bool
    {
        return !$this->isPayee() && self::STATUT_ANNULEE !== $this->statut;
    }

    private function recalculer(): void
    {
        $this->totalCentimes = array_sum($this->lignes->map(static fn (CommandeLigne $l) => $l->getTotalCentimes())->toArray());
    }
}

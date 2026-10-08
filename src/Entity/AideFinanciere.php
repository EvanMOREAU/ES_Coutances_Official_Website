<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Aide qui prend en charge une partie de la licence (Pass'Sport, Spot 50, Atout Normandie…).
 * Les demandes se font après réception des licences : l'aide reste « attendue » jusqu'à son encaissement.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'aide_financiere')]
class AideFinanciere
{
    public const TYPES = [
        'pass_sport'       => "Pass'Sport",
        'sport_50'         => 'Spot 50',
        'atout_normandie'  => 'Atout Normandie',
        'bourse_evasion'   => 'Bourse Évasion',
        'cheques_vacances' => 'Chèques vacances',
        'coupons_sport'    => 'Coupons sport',
    ];

    public const STATUT_ATTENDUE = 'attendue';
    public const STATUT_RECUE    = 'recue';

    /** Une aide reçue (telle qu'enregistrée en base) est définitive : type, montant, date et note ne bougent plus. */
    private bool $locked = false;

    #[ORM\PostLoad]
    public function lockIfReceived(): void
    {
        $this->locked = self::STATUT_RECUE === $this->statut;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Adhesion::class, inversedBy: 'aides')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Adhesion $adhesion = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(callback: 'typeKeys', message: 'Choisissez une aide.')]
    private string $type = 'pass_sport';

    #[ORM\Column]
    #[Assert\Positive(message: 'Indiquez un montant.')]
    private int $montantCentimes = 0;

    #[ORM\Column(length: 20, options: ['default' => 'attendue'])]
    private string $statut = self::STATUT_ATTENDUE;

    /** Date à laquelle l'aide a été reçue. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateReception = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    /** @return list<string> */
    public static function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdhesion(): ?Adhesion
    {
        return $this->adhesion;
    }
    public function setAdhesion(?Adhesion $adhesion): static
    {
        $this->adhesion = $adhesion;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $type): static
    {
        if ($this->locked) {
            return $this;
        } $this->type = $type;

        return $this;
    }
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
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

    public function getStatut(): string
    {
        return $this->statut;
    }
    public function setStatut(string $statut): static
    {
        if ($this->locked) {
            return $this;
        } $this->statut = $statut;

        return $this;
    }
    public function isRecue(): bool
    {
        return self::STATUT_RECUE === $this->statut;
    }

    public function getDateReception(): ?\DateTimeImmutable
    {
        return $this->dateReception;
    }
    public function setDateReception(?\DateTimeImmutable $d): static
    {
        if ($this->locked) {
            return $this;
        } $this->dateReception = $d;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
    public function setNote(?string $note): static
    {
        if ($this->locked) {
            return $this;
        } $this->note = $note;

        return $this;
    }
}

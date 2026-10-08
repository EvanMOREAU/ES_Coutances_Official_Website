<?php

namespace App\Entity;

use App\Entity\Concern\CreatedAtTrait;
use App\Entity\Concern\StatutTrait;
use App\Repository\LicencieRepository;
use App\Service\CategorieAge;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LicencieRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Licencie
{
    use CreatedAtTrait;
    use StatutTrait { setStatut as private setStatutBase; }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez le nom.')]
    #[Assert\Length(max: 100, maxMessage: 'Maximum 100 caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez le prénom.')]
    #[Assert\Length(max: 100, maxMessage: 'Maximum 100 caractères.')]
    private ?string $prenom = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la date de naissance.')]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\ManyToOne(targetEntity: Famille::class, inversedBy: 'licencies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Famille $famille = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Saison::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saison $saison = null;

    /**
     * Décalage manuel de la catégorie d'âge : +1 = surclassé (joue dans la
     * catégorie au-dessus de son âge), -1 = sous-classé, 0 = catégorie naturelle.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $decalageCategorie = 0;

    /**
     * @var Collection<int, Equipe>
     */
    #[ORM\ManyToMany(targetEntity: Equipe::class)]
    private Collection $equipes;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // --- Champs issus de l'import FFF (logiciel "Foot Club") ------------------

    /** Identifiant FFF stable de la personne : clé de réimport (retrouver ce licencié d'un export à l'autre). */
    #[ORM\Column(length: 30, nullable: true, unique: true)]
    private ?string $numeroPersonne = null;

    #[ORM\Column(length: 30, nullable: true, unique: true)]
    private ?string $numeroLicence = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $civilite = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieuNaissance = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $sexe = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nationalite = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $typeLicence = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codeCategorie = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    /** Email individuel du licencié tel que présent dans l'export (peut être vide ou celui du représentant légal). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $emailIndividuel = null;

    public function __construct()
    {
        $this->equipes = new ArrayCollection();
    }

    public function __toString(): string
    {
        return trim(sprintf('%s %s', $this->prenom, $this->nom));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getFamille(): ?Famille
    {
        return $this->famille;
    }

    public function setFamille(?Famille $famille): static
    {
        $this->famille = $famille;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getDecalageCategorie(): int
    {
        return $this->decalageCategorie;
    }

    public function setDecalageCategorie(?int $decalageCategorie): static
    {
        $this->decalageCategorie = $decalageCategorie ?? 0;

        return $this;
    }

    /** Catégorie d'âge "naturelle" (sans décalage), calculée depuis la date de naissance. */
    public function getCategorieNaturelle(): ?string
    {
        return $this->dateNaissance ? CategorieAge::calculer($this->dateNaissance, $this->saison) : null;
    }

    /** Catégorie effective (avec surclassement / sous-classement éventuel). */
    public function getCategorie(): ?string
    {
        return $this->dateNaissance ? CategorieAge::calculer($this->dateNaissance, $this->saison, $this->decalageCategorie) : null;
    }

    /**
     * @return Collection<int, Equipe>
     */
    public function getEquipes(): Collection
    {
        return $this->equipes;
    }

    public function addEquipe(Equipe $equipe): static
    {
        if (!$this->equipes->contains($equipe)) {
            $this->equipes->add($equipe);
        }

        return $this;
    }

    public function removeEquipe(Equipe $equipe): static
    {
        $this->equipes->removeElement($equipe);

        return $this;
    }

    /**
     * Licencié qui gère lui-même son compte (adulte sans parent) : il est le titulaire
     * de sa propre famille, donc il voit ses factures. Un mineur rattaché à un parent
     * n'a accès qu'à son planning et à ses informations.
     */
    public function isAutonome(): bool
    {
        return null !== $this->user && $this->famille?->getUser() === $this->user;
    }

    /** Licence en cours : licencié actif ET rattaché à une saison qui n'est pas terminée. */
    public function isEnCours(): bool
    {
        return $this->isActif() && !$this->isSaisonTerminee();
    }

    /** La saison du licencié est terminée : il n'est plus actif et son compte est bloqué. */
    public function isSaisonTerminee(): bool
    {
        $fin = $this->saison?->getDateFin();

        return null !== $fin && $fin < new \DateTimeImmutable('today');
    }

    public function isActif(): bool
    {
        return $this->actif;
    }


    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getNumeroPersonne(): ?string
    {
        return $this->numeroPersonne;
    }

    public function setNumeroPersonne(?string $numeroPersonne): static
    {
        $this->numeroPersonne = $numeroPersonne;

        return $this;
    }

    public function getNumeroLicence(): ?string
    {
        return $this->numeroLicence;
    }

    public function setNumeroLicence(?string $numeroLicence): static
    {
        $this->numeroLicence = $numeroLicence;

        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): static
    {
        $this->civilite = $civilite;

        return $this;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function setLieuNaissance(?string $lieuNaissance): static
    {
        $this->lieuNaissance = $lieuNaissance;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getNationalite(): ?string
    {
        return $this->nationalite;
    }

    public function setNationalite(?string $nationalite): static
    {
        $this->nationalite = $nationalite;

        return $this;
    }

    public function getTypeLicence(): ?string
    {
        return $this->typeLicence;
    }

    public function setTypeLicence(?string $typeLicence): static
    {
        $this->typeLicence = $typeLicence;

        return $this;
    }

    public function getCodeCategorie(): ?string
    {
        return $this->codeCategorie;
    }

    public function setCodeCategorie(?string $codeCategorie): static
    {
        $this->codeCategorie = $codeCategorie;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmailIndividuel(): ?string
    {
        return $this->emailIndividuel;
    }

    public function setEmailIndividuel(?string $emailIndividuel): static
    {
        $this->emailIndividuel = $emailIndividuel;

        return $this;
    }

    /** Vrai uniquement quand le statut est "actif" (utilisé par le site public). */
    public function setStatut(string $statut): static
    {
        $this->setStatutBase($statut);
        $this->actif = self::STATUT_ACTIVE === $statut;

        return $this;
    }

    /** Faux si le compte du licencié n'a pas d'adresse email exploitable (invalide, ou provisoire d'import). */
    public function hasEmailValide(): bool
    {
        $email = $this->user?->getEmail();
        if (!$email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return !str_ends_with(strtolower($email), '@import.local');
    }

    public function setActif(bool $actif): static
    {
        if ($actif) {
            return $this->setStatut(self::STATUT_ACTIVE);
        }

        return $this->setStatut(self::STATUT_ACTIVE === $this->getStatut() ? self::STATUT_ARCHIVED : $this->getStatut());
    }
}

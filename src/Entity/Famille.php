<?php

namespace App\Entity;

use App\Entity\Concern\CreatedAtTrait;
use App\Entity\Concern\StatutTrait;
use App\Repository\FamilleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FamilleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Famille
{
    use CreatedAtTrait;
    use StatutTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Indiquez le nom de la famille.')]
    #[Assert\Length(max: 150, maxMessage: 'Maximum 150 caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $civilite = null;

    // --- Représentant légal 2 (import FFF) : contact seulement, pas de compte ---

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nomReprLegal2 = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephoneReprLegal2 = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $emailReprLegal2 = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    /**
     * @var Collection<int, Licencie>
     */
    #[ORM\OneToMany(targetEntity: Licencie::class, mappedBy: 'famille')]
    private Collection $licencies;

    public function __construct()
    {
        $this->licencies = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getNomAffiche();
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
        $this->nom = mb_strtoupper(trim($nom), 'UTF-8');

        return $this;
    }

    /**
     * Nom affiché dans l'interface : "(Prénom) NOM", le prénom entre parenthèses étant celui du
     * parent référent (compte de connexion de la famille), pour distinguer deux familles homonymes.
     */
    public function getNomAffiche(): string
    {
        $prenom = $this->user?->getPrenom();

        return ($prenom ? '('.$prenom.') ' : '').($this->nom ?? '');
    }

    /**
     * Faux si la famille n'a pas d'adresse email exploitable : ni format invalide, ni adresse
     * provisoire générée par l'import (@import.local, cf. FootClubImportApplier::uniqueEmail).
     */
    public function hasEmailValide(): bool
    {
        $email = $this->user?->getEmail();
        if (!$email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return !str_ends_with(strtolower($email), '@import.local');
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = null !== $ville ? mb_strtoupper(trim($ville), 'UTF-8') : null;

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

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): static
    {
        $this->civilite = $civilite;

        return $this;
    }

    public function getNomReprLegal2(): ?string
    {
        return $this->nomReprLegal2;
    }

    public function setNomReprLegal2(?string $nomReprLegal2): static
    {
        $this->nomReprLegal2 = $nomReprLegal2;

        return $this;
    }

    public function getTelephoneReprLegal2(): ?string
    {
        return $this->telephoneReprLegal2;
    }

    public function setTelephoneReprLegal2(?string $telephoneReprLegal2): static
    {
        $this->telephoneReprLegal2 = $telephoneReprLegal2;

        return $this;
    }

    public function getEmailReprLegal2(): ?string
    {
        return $this->emailReprLegal2;
    }

    public function setEmailReprLegal2(?string $emailReprLegal2): static
    {
        $this->emailReprLegal2 = $emailReprLegal2;

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

    /**
     * @return Collection<int, Licencie>
     */
    public function getLicencies(): Collection
    {
        return $this->licencies;
    }

    public function addLicencie(Licencie $licencie): static
    {
        if (!$this->licencies->contains($licencie)) {
            $this->licencies->add($licencie);
            $licencie->setFamille($this);
        }

        return $this;
    }

    public function removeLicencie(Licencie $licencie): static
    {
        $this->licencies->removeElement($licencie);

        return $this;
    }
}

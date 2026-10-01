<?php

namespace App\Entity;

use App\Repository\PartenaireRepository;
use App\Entity\Concern\CreatedAtTrait;
use App\Entity\Concern\StatutTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: PartenaireRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Partenaire
{
    use CreatedAtTrait;
    use StatutTrait { setStatut as private setStatutBase; }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoName = null;

    #[Vich\UploadableField(mapping: 'partenaire_logo', fileNameProperty: 'logoName')]
    private ?File $logoFile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private int $ordre = 0;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: CategoriePartenaire::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CategoriePartenaire $categorie = null;

    /** @var Collection<int, ContratPartenaire> */
    #[ORM\OneToMany(targetEntity: ContratPartenaire::class, mappedBy: 'partenaire', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $contrats;

    public function __construct()
    {
        $this->contrats = new ArrayCollection();
    }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): static { $this->url = $url; return $this; }
    public function getId(): ?int { return $this->id; }

    public function getCategorie(): ?CategoriePartenaire { return $this->categorie; }
    public function setCategorie(?CategoriePartenaire $categorie): static { $this->categorie = $categorie; return $this; }

    /** @return Collection<int, ContratPartenaire> */
    public function getContrats(): Collection { return $this->contrats; }

    public function addContrat(ContratPartenaire $c): static
    {
        if (!$this->contrats->contains($c)) {
            $this->contrats->add($c);
            $c->setPartenaire($this);
        }

        return $this;
    }

    public function removeContrat(ContratPartenaire $c): static { $this->contrats->removeElement($c); return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getLogoName(): ?string { return $this->logoName; }
    public function setLogoName(?string $logoName): static { $this->logoName = $logoName; return $this; }

    public function getLogoFile(): ?File { return $this->logoFile; }
    public function setLogoFile(?File $logoFile = null): void
    {
        $this->logoFile = $logoFile;
        if (null !== $logoFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function isActif(): bool { return $this->actif; }

    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }

    public function __toString(): string { return $this->nom ?? ''; }

    /** Vrai uniquement quand le statut est "actif" (utilisé par le site public). */
    public function setStatut(string $statut): static
    {
        $this->setStatutBase($statut);
        $this->actif = self::STATUT_ACTIVE === $statut;

        return $this;
    }

    public function setActif(bool $actif): static
    {
        if ($actif) {
            return $this->setStatut(self::STATUT_ACTIVE);
        }

        return $this->setStatut(self::STATUT_ACTIVE === $this->getStatut() ? self::STATUT_ARCHIVED : $this->getStatut());
    }
}

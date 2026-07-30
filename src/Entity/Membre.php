<?php

namespace App\Entity;

use App\Repository\MembreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: MembreRepository::class)]
class Membre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 150)]
    private ?string $poste = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoName = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\Column]
    private ?bool $actif = null;

    #[Vich\UploadableField(mapping: 'membre_photo', fileNameProperty: 'photoName')]
    private ?File $photoFile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $diplome = null;

    /**
     * @var Collection<int, Categorie>
     */
    #[ORM\ManyToMany(targetEntity: Categorie::class, inversedBy: 'membres')]
    private Collection $categories;

    public function setPhotoFile(?File $photoFile = null): void
    {
        $this->photoFile = $photoFile;
        if (null !== $photoFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
    public function getPhotoFile(): ?File { return $this->photoFile; }
    public function setPhotoName(?string $photoName): void { $this->photoName = $photoName; }
    public function getPhotoName(): ?string { return $this->photoName; }
    public function setUpdatedAt(?\DateTimeImmutable $u): void { $this->updatedAt = $u; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function __construct()
    {
        $this->actif = true;
        $this->ordre = 0;
        $this->categories = new ArrayCollection();
    }

    public function __toString(): string { return $this->nom ?? ''; }

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

    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(string $poste): static
    {
        $this->poste = $poste;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getDiplome(): ?string
    {
        return $this->diplome;
    }

    public function setDiplome(?string $diplome): static
    {
        $this->diplome = $diplome;

        return $this;
    }

    /**
     * @return Collection<int, Categorie>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Categorie $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Categorie $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }
}

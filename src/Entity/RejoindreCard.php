<?php

namespace App\Entity;

use App\Repository\RejoindreCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: RejoindreCardRepository::class)]
#[Vich\Uploadable]
class RejoindreCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Donnez un titre à la carte.')]
    #[Assert\Length(max: 150, maxMessage: 'Maximum 150 caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'rejoindre_image', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $boutonTexte = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $boutonUrl = null;

    #[ORM\ManyToOne(targetEntity: PageContenu::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PageContenu $pageDetail = null;

    /**
     * Champ non persisté : titre d'une nouvelle page de détail à créer automatiquement
     * à l'enregistrement, si aucune page existante n'est sélectionnée dans pageDetail.
     */
    private ?string $nouvellePageTitre = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private int $ordre = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }
    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }
    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }
    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getBoutonTexte(): ?string
    {
        return $this->boutonTexte;
    }
    public function setBoutonTexte(?string $boutonTexte): static
    {
        $this->boutonTexte = $boutonTexte;

        return $this;
    }

    public function getBoutonUrl(): ?string
    {
        return $this->boutonUrl;
    }
    public function setBoutonUrl(?string $boutonUrl): static
    {
        $this->boutonUrl = $boutonUrl;

        return $this;
    }

    public function getPageDetail(): ?PageContenu
    {
        return $this->pageDetail;
    }
    public function setPageDetail(?PageContenu $pageDetail): static
    {
        $this->pageDetail = $pageDetail;

        return $this;
    }

    public function getNouvellePageTitre(): ?string
    {
        return $this->nouvellePageTitre;
    }
    public function setNouvellePageTitre(?string $nouvellePageTitre): static
    {
        $this->nouvellePageTitre = $nouvellePageTitre;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }
    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }
    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function __toString(): string
    {
        return $this->titre ?? '';
    }
}

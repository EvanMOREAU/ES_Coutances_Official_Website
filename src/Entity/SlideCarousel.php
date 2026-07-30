<?php

namespace App\Entity;

use App\Repository\SlideCarouselRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: SlideCarouselRepository::class)]
#[Vich\Uploadable]
class SlideCarousel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $titre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $titreMot = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sousTitre = null;

    #[ORM\Column(length: 255)]
    private ?string $imageName = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\Column]
    private ?bool $actif = null;

    public function __construct()
    {
        $this->actif = false;  // ou false selon ta préférence
        $this->ordre = 0;
    }
    
    public function __toString(): string
    {
        return $this->titre ?? '';
    }

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

    public function getTitreMot(): ?string
    {
        return $this->titreMot;
    }

    public function setTitreMot(?string $titreMot): static
    {
        $this->titreMot = $titreMot;

        return $this;
    }

    public function getSousTitre(): ?string
    {
        return $this->sousTitre;
    }

    public function setSousTitre(?string $sousTitre): static
    {
        $this->sousTitre = $sousTitre;

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

    #[Vich\UploadableField(mapping: 'slide_image', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageName(?string $imageName): void { $this->imageName = $imageName; }
    public function getImageName(): ?string { return $this->imageName; }
    public function setUpdatedAt(?\DateTimeImmutable $u): void { $this->updatedAt = $u; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}

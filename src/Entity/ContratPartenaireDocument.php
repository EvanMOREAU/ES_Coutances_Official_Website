<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/** Document hébergé pour un contrat partenaire (contrat signé, devis, facture…). */
#[ORM\Entity]
#[ORM\Table(name: 'contrat_partenaire_document')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class ContratPartenaireDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ContratPartenaire::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ContratPartenaire $contrat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichierName = null;

    #[Vich\UploadableField(mapping: 'contrat_partenaire_document', fileNameProperty: 'fichierName')]
    private ?File $fichierFile = null;

    /** Nom d'origine du fichier, pour l'affichage (le nom stocké est rendu unique). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomOriginal = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getContrat(): ?ContratPartenaire { return $this->contrat; }
    public function setContrat(?ContratPartenaire $contrat): static { $this->contrat = $contrat; return $this; }

    public function getFichierName(): ?string { return $this->fichierName; }
    public function setFichierName(?string $fichierName): static { $this->fichierName = $fichierName; return $this; }

    public function getFichierFile(): ?File { return $this->fichierFile; }
    public function setFichierFile(?File $fichierFile = null): void
    {
        $this->fichierFile = $fichierFile;
        if (null !== $fichierFile) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getNomOriginal(): ?string { return $this->nomOriginal; }
    public function setNomOriginal(?string $nomOriginal): static { $this->nomOriginal = $nomOriginal; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}

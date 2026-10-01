<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Action à réaliser dans le cadre d'un contrat partenaire (ex. envoyer le flocage, poser le panneau). */
#[ORM\Entity]
#[ORM\Table(name: 'contrat_partenaire_tache')]
class ContratPartenaireTache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ContratPartenaire::class, inversedBy: 'taches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ContratPartenaire $contrat = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Indiquez le libellé de la tâche.')]
    private ?string $titre = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $echeance = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $fait = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $ordre = 0;

    public function getId(): ?int { return $this->id; }

    public function getContrat(): ?ContratPartenaire { return $this->contrat; }
    public function setContrat(?ContratPartenaire $contrat): static { $this->contrat = $contrat; return $this; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $titre): static { $this->titre = $titre; return $this; }

    public function getEcheance(): ?\DateTimeImmutable { return $this->echeance; }
    public function setEcheance(?\DateTimeImmutable $echeance): static { $this->echeance = $echeance; return $this; }

    public function isFait(): bool { return $this->fait; }
    public function setFait(bool $fait): static { $this->fait = $fait; return $this; }

    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }
}

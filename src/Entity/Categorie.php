<?php

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?int $ordre = null;

    /**
     * @var Collection<int, Membre>
     */
    #[ORM\ManyToMany(targetEntity: Membre::class, mappedBy: 'categories')]
    private Collection $membres;

    public function __construct()
    {
        $this->membres = new \Doctrine\Common\Collections\ArrayCollection();
        $this->ordre = 0;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    /**
     * @return Collection<int, Membre>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(Membre $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->addCategory($this);
        }

        return $this;
    }

    public function removeMembre(Membre $membre): static
    {
        if ($this->membres->removeElement($membre)) {
            $membre->removeCategory($this);
        }

        return $this;
    }
}

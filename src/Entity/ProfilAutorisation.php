<?php

namespace App\Entity;

use App\Repository\ProfilAutorisationRepository;
use App\Security\PermissionCatalog;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Profil d'autorisation : un ensemble d'autorisations prédéfini (ex. « Secrétariat »,
 * « Boutique ») que l'on attribue en un clic à un utilisateur du back-office.
 */
#[ORM\Entity(repositoryClass: ProfilAutorisationRepository::class)]
#[ORM\Table(name: 'profil_autorisation')]
#[UniqueEntity(fields: ['nom'], message: 'Un profil porte déjà ce nom.')]
class ProfilAutorisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Donnez un nom au profil.')]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

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

    /** @return list<string> */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
    /** @param iterable<string> $permissions */
    public function setPermissions(iterable $permissions): static
    {
        $this->permissions = PermissionCatalog::sanitize($permissions);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}

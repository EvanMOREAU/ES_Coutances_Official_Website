<?php

namespace App\Entity;

use App\Repository\FileFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

/** Fichier ou dossier épinglé (« favori ») par un utilisateur dans le gestionnaire de fichiers. */
#[ORM\Entity(repositoryClass: FileFavoriteRepository::class)]
#[ORM\Table(name: 'file_favorite')]
#[ORM\UniqueConstraint(name: 'uniq_file_favorite', columns: ['user_id', 'path'])]
class FileFavorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Chemin virtuel du gestionnaire (ex. « documents/Joueurs/certificat.pdf »). */
    #[ORM\Column(length: 500)]
    private string $path;

    public function __construct(User $user, string $path)
    {
        $this->user = $user;
        $this->path = $path;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): static { $this->path = $path; return $this; }
}

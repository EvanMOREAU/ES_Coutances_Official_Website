<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarName = null;

    #[Vich\UploadableField(mapping: 'user_avatar', fileNameProperty: 'avatarName')]
    private ?File $avatarFile = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 20)]
    private string $theme = 'dark';

    #[ORM\Column(length: 20)]
    private string $colorScheme = 'rouge';

    #[ORM\Column(length: 20)]
    private string $density = 'comfortable';

    #[ORM\Column]
    private array $notificationPreferences = ['new_famille'];

    /**
     * Préférences d'affichage des tableaux de l'admin, par tableau :
     * ['famille' => ['hidden' => ['ville'], 'perPage' => 25], ...].
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tablePreferences = null;

    /** Dernière activité dans la messagerie (sert à l'indicateur « en ligne »). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    /** Profil d'autorisation attribué (pré-autorisation). */
    #[ORM\ManyToOne(targetEntity: ProfilAutorisation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProfilAutorisation $profil = null;

    /** Autorisations accordées en plus de celles du profil. @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $permissionsAjoutees = [];

    /** Autorisations retirées à celles du profil. @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $permissionsRetirees = [];

    /**
     * Faux (défaut) : un administrateur garde tous les accès, y compris les futurs. Vrai : ses accès
     * sont ceux de son profil, ajustés par les listes ci-dessus (même si elles sont vides).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $accesRestreint = false;

    public function getProfil(): ?ProfilAutorisation { return $this->profil; }
    public function setProfil(?ProfilAutorisation $profil): static { $this->profil = $profil; return $this; }

    /** @return list<string> */
    public function getPermissionsAjoutees(): array { return $this->permissionsAjoutees; }
    /** @param list<string> $permissions */
    public function setPermissionsAjoutees(array $permissions): static { $this->permissionsAjoutees = array_values($permissions); return $this; }

    /** @return list<string> */
    public function getPermissionsRetirees(): array { return $this->permissionsRetirees; }
    /** @param list<string> $permissions */
    public function setPermissionsRetirees(array $permissions): static { $this->permissionsRetirees = array_values($permissions); return $this; }

    public function isAccesRestreint(): bool { return $this->accesRestreint; }
    public function setAccesRestreint(bool $restreint): static { $this->accesRestreint = $restreint; return $this; }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER'; // garanti pour tout utilisateur
        return array_unique($roles);
    }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): static
    {
        // Le formulaire d'édition (EasyAdmin) soumet le champ mot de passe vide
        // (donc null) quand on ne veut pas le changer : on ignore ce cas pour
        // ne pas écraser le mot de passe existant.
        if ($password !== null && $password !== '') {
            $this->password = $password;
        }

        return $this;
    }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(?string $prenom): static { $this->prenom = $prenom ?: null; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): static { $this->bio = $bio ?: null; return $this; }

    /** Nom affiché dans l'interface : "Prénom Nom" (ou juste le nom si pas de prénom). */
    public function getNomComplet(): string
    {
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }

    public function getInitiale(): string
    {
        return mb_strtoupper(mb_substr($this->prenom ?: ($this->nom ?? '?'), 0, 1));
    }

    public function getAvatarName(): ?string { return $this->avatarName; }
    public function setAvatarName(?string $avatarName): void { $this->avatarName = $avatarName; }

    public function getAvatarFile(): ?File { return $this->avatarFile; }
    public function setAvatarFile(?File $avatarFile = null): void
    {
        $this->avatarFile = $avatarFile;
        if (null !== $avatarFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void { $this->updatedAt = $updatedAt; }

    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): static { $this->theme = $theme; return $this; }

    public function getColorScheme(): string { return $this->colorScheme; }
    public function setColorScheme(string $colorScheme): static { $this->colorScheme = $colorScheme; return $this; }

    public function getDensity(): string { return $this->density; }
    public function setDensity(string $density): static { $this->density = $density; return $this; }

    public function getNotificationPreferences(): array { return $this->notificationPreferences; }
    public function setNotificationPreferences(array $notificationPreferences): static { $this->notificationPreferences = $notificationPreferences; return $this; }
    public function hasNotificationPreference(string $key): bool { return in_array($key, $this->notificationPreferences, true); }

    public function getTablePreferences(): array { return $this->tablePreferences ?? []; }
    public function setTablePreferences(?array $tablePreferences): static { $this->tablePreferences = $tablePreferences; return $this; }

    public function getLastSeenAt(): ?\DateTimeImmutable { return $this->lastSeenAt; }
    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static { $this->lastSeenAt = $lastSeenAt; return $this; }

    /** Compte de l'équipe du club (encadrant, administrateur ou développeur), par opposition à un compte famille / licencié. */
    public function isStaff(): bool
    {
        return (bool) array_intersect($this->getRoles(), ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_DEV']);
    }

    public function eraseCredentials(): void {}

    public function __toString(): string { return $this->email ?? ''; }
}
<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TotpTwoFactorInterface, EmailTwoFactorInterface, BackupCodeInterface
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

    /** Choix de notification : ['bell' => [clé => bool], 'email' => [clé => bool]] (voir NotificationPreferences). */
    #[ORM\Column]
    private array $notificationPreferences = [];

    /** Date d'anonymisation du compte (RGPD) : le compte est vidé de ses données personnelles, les factures restent. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

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

    /** Secret TOTP (base32), présent seulement si l'authentification par application est activée. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    /** Code de vérification envoyé par e-mail, en attente de saisie. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $emailAuthCode = null;

    /** Faux par défaut : l'authentification par e-mail n'est activée qu'après un choix explicite de l'utilisateur. */
    #[ORM\Column(options: ['default' => false])]
    private bool $emailAuthEnabled = false;

    /** Codes de secours à usage unique (hachés), générés à la demande depuis les paramètres de sécurité. @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $backupCodes = [];

    /** Dernière version du changelog consultée par cet utilisateur (voir ChangelogController). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $changelogVersionVue = null;

    /**
     * Identifiant opaque et stable utilisé comme « user handle » WebAuthn (voir WebauthnService),
     * généré à la première clé d'accès enregistrée. Jamais réaffiché, sans lien visible avec l'email.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $webauthnUserHandle = null;

    public function getWebauthnUserHandle(): ?string { return $this->webauthnUserHandle; }
    public function setWebauthnUserHandle(?string $handle): static { $this->webauthnUserHandle = $handle; return $this; }

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
    /** Un nouveau compte hors équipe (famille, licencié, client) démarre en thème clair, comme l'espace Mon compte d'origine. */
    #[ORM\PrePersist]
    public function defaultPortalTheme(): void
    {
        if (!$this->isStaff() && 'dark' === $this->theme) {
            $this->theme = 'light';
        }
    }

    public function isAnonymized(): bool { return $this->anonymizedAt !== null; }
    public function getAnonymizedAt(): ?\DateTimeImmutable { return $this->anonymizedAt; }
    public function markAnonymized(): static { $this->anonymizedAt = new \DateTimeImmutable(); return $this; }

    public function getTablePreferences(): array { return $this->tablePreferences ?? []; }
    public function setTablePreferences(?array $tablePreferences): static { $this->tablePreferences = $tablePreferences; return $this; }

    public function getLastSeenAt(): ?\DateTimeImmutable { return $this->lastSeenAt; }
    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static { $this->lastSeenAt = $lastSeenAt; return $this; }

    /** Compte de l'équipe du club (encadrant, administrateur ou développeur), par opposition à un compte famille / licencié. */
    public function isStaff(): bool
    {
        return (bool) array_intersect($this->getRoles(), ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_DEV']);
    }

    // --- Authentification à deux facteurs : application (TOTP) ---

    public function isTotpAuthenticationEnabled(): bool { return null !== $this->totpSecret; }

    public function getTotpAuthenticationUsername(): ?string { return $this->email; }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getTotpSecret(): ?string { return $this->totpSecret; }
    public function setTotpSecret(?string $totpSecret): static { $this->totpSecret = $totpSecret; return $this; }

    // --- Authentification à deux facteurs : code par e-mail ---

    public function isEmailAuthEnabled(): bool { return $this->emailAuthEnabled; }
    public function setEmailAuthEnabled(bool $emailAuthEnabled): static { $this->emailAuthEnabled = $emailAuthEnabled; return $this; }

    public function getEmailAuthRecipient(): string { return (string) $this->email; }

    public function getEmailAuthCode(): ?string { return $this->emailAuthCode; }
    public function setEmailAuthCode(string $authCode): void { $this->emailAuthCode = $authCode; }

    // --- Authentification à deux facteurs : codes de secours ---

    /** @return list<string> hachages des codes de secours restants */
    public function getBackupCodes(): array { return $this->backupCodes; }
    /** @param list<string> $hashedCodes */
    public function setBackupCodes(array $hashedCodes): static { $this->backupCodes = array_values($hashedCodes); return $this; }

    public function isBackupCode(string $code): bool
    {
        foreach ($this->backupCodes as $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                return true;
            }
        }

        return false;
    }

    public function invalidateBackupCode(string $code): void
    {
        $this->backupCodes = array_values(array_filter(
            $this->backupCodes,
            static fn (string $hashedCode): bool => !password_verify($code, $hashedCode),
        ));
    }

    public function getChangelogVersionVue(): ?string { return $this->changelogVersionVue; }
    public function setChangelogVersionVue(?string $version): static { $this->changelogVersionVue = $version; return $this; }

    /** Compte de l'équipe du club ayant activé au moins une méthode de double authentification. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->isTotpAuthenticationEnabled() || $this->isEmailAuthEnabled();
    }

    public function eraseCredentials(): void {}

    public function __toString(): string { return $this->email ?? ''; }
}
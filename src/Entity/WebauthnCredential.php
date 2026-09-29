<?php

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\CredentialRecord;

/**
 * Clé d'accès (passkey / WebAuthn) enregistrée par un utilisateur du back-office, permettant de
 * se connecter sans mot de passe (voir Security\Webauthn\PasskeyAuthenticator).
 */
#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Identifiant de la clé, encodé en base64url (voir CredentialRecord::$publicKeyCredentialId). */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $credentialId = null;

    /** CredentialRecord de la bibliothèque WebAuthn, sérialisé (PHP serialize). */
    #[ORM\Column(type: 'text')]
    private ?string $donnees = null;

    /** Nom donné par l'utilisateur pour reconnaître cette clé (ex. « iPhone de Marie »). */
    #[ORM\Column(length: 100)]
    private ?string $label = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getCredentialId(): ?string { return $this->credentialId; }
    public function setCredentialId(string $credentialId): static { $this->credentialId = $credentialId; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
    public function marquerUtilisee(): static { $this->lastUsedAt = new \DateTimeImmutable(); return $this; }

    public function getRecord(): CredentialRecord
    {
        $record = unserialize((string) $this->donnees, ['allowed_classes' => true]);
        if (!$record instanceof CredentialRecord) {
            throw new \RuntimeException('Enregistrement de clé d\'accès illisible.');
        }

        return $record;
    }

    public function setRecord(CredentialRecord $record): static
    {
        $this->donnees = serialize($record);

        return $this;
    }
}

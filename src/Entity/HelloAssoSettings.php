<?php

namespace App\Entity;

use App\Repository\HelloAssoSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Identifiants d'accès à l'API HelloAsso (paiement en ligne de la boutique), modifiables depuis
 * l'administration. Une seule ligne. Le client secret est stocké chiffré (voir SecretBox).
 */
#[ORM\Entity(repositoryClass: HelloAssoSettingsRepository::class)]
#[ORM\Table(name: 'helloasso_settings')]
class HelloAssoSettings
{
    public const ENV_SANDBOX    = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    public const ENVIRONNEMENTS = [
        self::ENV_SANDBOX    => 'Sandbox (test, api.helloasso-sandbox.com)',
        self::ENV_PRODUCTION => 'Production (api.helloasso.com)',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $actif = false;

    #[ORM\Column(length: 20, options: ['default' => 'sandbox'])]
    private string $environnement = self::ENV_SANDBOX;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $clientId = null;

    /** Client secret chiffré (voir SecretBox). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $clientSecretChiffre = null;

    /** Slug de l'organisation HelloAsso (visible dans son URL d'administration). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $organisationSlug = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int { return $this->id; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getEnvironnement(): string { return $this->environnement; }
    public function setEnvironnement(string $environnement): static
    {
        $this->environnement = isset(self::ENVIRONNEMENTS[$environnement]) ? $environnement : self::ENV_SANDBOX;

        return $this;
    }
    public function isSandbox(): bool { return self::ENV_SANDBOX === $this->environnement; }

    public function getClientId(): ?string { return $this->clientId; }
    public function setClientId(?string $clientId): static { $this->clientId = '' === trim((string) $clientId) ? null : trim((string) $clientId); return $this; }

    public function getClientSecretChiffre(): ?string { return $this->clientSecretChiffre; }
    public function setClientSecretChiffre(?string $value): static { $this->clientSecretChiffre = $value; return $this; }
    public function hasClientSecret(): bool { return null !== $this->clientSecretChiffre; }

    public function getOrganisationSlug(): ?string { return $this->organisationSlug; }
    public function setOrganisationSlug(?string $slug): static { $this->organisationSlug = '' === trim((string) $slug) ? null : trim((string) $slug); return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    /** Réglages complets et activés : utilisables pour créer une intention de paiement. */
    public function isUtilisable(): bool
    {
        return $this->actif
            && null !== $this->clientId
            && $this->hasClientSecret()
            && null !== $this->organisationSlug;
    }
}

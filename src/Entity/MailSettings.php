<?php

namespace App\Entity;

use App\Repository\MailSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réglages d'envoi des e-mails (serveur SMTP), modifiables depuis l'administration.
 * Une seule ligne. Tant que « actif » est faux, l'application utilise le serveur
 * configuré dans l'environnement (MAILER_DSN). Le mot de passe est stocké chiffré.
 */
#[ORM\Entity(repositoryClass: MailSettingsRepository::class)]
#[ORM\Table(name: 'mail_settings')]
class MailSettings
{
    public const CHIFFREMENT_AUCUN = 'none';
    public const CHIFFREMENT_TLS   = 'tls';
    public const CHIFFREMENT_SSL   = 'ssl';

    public const CHIFFREMENTS = [
        self::CHIFFREMENT_TLS   => 'STARTTLS (port 587, recommandé)',
        self::CHIFFREMENT_SSL   => 'SSL/TLS implicite (port 465)',
        self::CHIFFREMENT_AUCUN => 'Aucun (port 25 ou 1025, réseau de confiance)',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $actif = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $host = null;

    #[ORM\Column(options: ['default' => 587])]
    #[Assert\Range(min: 1, max: 65535, notInRangeMessage: 'Le port doit être compris entre 1 et 65535.')]
    private int $port = 587;

    #[ORM\Column(length: 10, options: ['default' => 'tls'])]
    private string $chiffrement = self::CHIFFREMENT_TLS;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    /** Mot de passe chiffré (voir SecretBox). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $passwordChiffre = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $verifierCertificat = true;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse d\'expédition invalide.')]
    private ?string $expediteurAdresse = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $expediteurNom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int { return $this->id; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getHost(): ?string { return $this->host; }
    public function setHost(?string $host): static { $this->host = null === $host ? null : trim($host); return $this; }

    public function getPort(): int { return $this->port; }
    public function setPort(?int $port): static { $this->port = $port ?: 587; return $this; }

    public function getChiffrement(): string { return $this->chiffrement; }
    public function setChiffrement(string $chiffrement): static { $this->chiffrement = isset(self::CHIFFREMENTS[$chiffrement]) ? $chiffrement : self::CHIFFREMENT_TLS; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): static { $this->username = '' === trim((string) $username) ? null : trim((string) $username); return $this; }

    public function getPasswordChiffre(): ?string { return $this->passwordChiffre; }
    public function setPasswordChiffre(?string $value): static { $this->passwordChiffre = $value; return $this; }

    public function isVerifierCertificat(): bool { return $this->verifierCertificat; }
    public function setVerifierCertificat(bool $verifier): static { $this->verifierCertificat = $verifier; return $this; }

    public function getExpediteurAdresse(): ?string { return $this->expediteurAdresse; }
    public function setExpediteurAdresse(?string $adresse): static { $this->expediteurAdresse = '' === trim((string) $adresse) ? null : trim((string) $adresse); return $this; }

    public function getExpediteurNom(): ?string { return $this->expediteurNom; }
    public function setExpediteurNom(?string $nom): static { $this->expediteurNom = '' === trim((string) $nom) ? null : trim((string) $nom); return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    public function hasPassword(): bool { return null !== $this->passwordChiffre; }

    /** Réglages utilisables pour envoyer : activés et serveur renseigné. */
    public function isUtilisable(): bool { return $this->actif && '' !== trim((string) $this->host); }
}

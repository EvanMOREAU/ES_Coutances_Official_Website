<?php

namespace App\Entity;

use App\Repository\DeploymentRepository;
use Doctrine\ORM\Mapping as ORM;

/** Historique des mises à jour déployées depuis l'admin (voir DeployService). */
#[ORM\Entity(repositoryClass: DeploymentRepository::class)]
class Deployment
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_RUNNING;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $fromCommit = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $toCommit = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $triggeredBy = null;

    /** Étape en cours (affichée dans l'interface pendant le déploiement). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $step = null;

    #[ORM\Column(type: 'text')]
    private string $log = '';

    /** Nom du fichier de sauvegarde de la base prise avant la mise à jour (jamais supprimé). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbBackup = null;

    /** Nom de l'archive des fichiers du site prise avant la mise à jour (supprimable après validation). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filesBackup = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $filesBackupSize = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $filesBackupDeletedAt = null;

    /** Vrai si la mise à jour a échoué et que le site a été remis automatiquement dans son état d'avant. */
    #[ORM\Column(options: ['default' => false])]
    private bool $rolledBack = false;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getFromCommit(): ?string
    {
        return $this->fromCommit;
    }
    public function setFromCommit(?string $fromCommit): static
    {
        $this->fromCommit = $fromCommit;

        return $this;
    }

    public function getToCommit(): ?string
    {
        return $this->toCommit;
    }
    public function setToCommit(?string $toCommit): static
    {
        $this->toCommit = $toCommit;

        return $this;
    }

    public function getTriggeredBy(): ?string
    {
        return $this->triggeredBy;
    }
    public function setTriggeredBy(?string $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }

    public function getStep(): ?string
    {
        return $this->step;
    }
    public function setStep(?string $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getLog(): string
    {
        return $this->log;
    }
    public function setLog(string $log): static
    {
        $this->log = $log;

        return $this;
    }
    public function appendLog(string $text): static
    {
        $this->log .= $text;

        return $this;
    }

    /** Reprend tout l'état d'un autre déploiement (sauf l'identifiant) : sert après une restauration de la base. */
    public function restoreFrom(self $other): static
    {
        $this->status               = $other->status;
        $this->startedAt            = $other->startedAt;
        $this->finishedAt           = $other->finishedAt;
        $this->fromCommit           = $other->fromCommit;
        $this->toCommit             = $other->toCommit;
        $this->triggeredBy          = $other->triggeredBy;
        $this->step                 = $other->step;
        $this->log                  = $other->log;
        $this->dbBackup             = $other->dbBackup;
        $this->filesBackup          = $other->filesBackup;
        $this->filesBackupSize      = $other->filesBackupSize;
        $this->filesBackupDeletedAt = $other->filesBackupDeletedAt;
        $this->rolledBack           = $other->rolledBack;

        return $this;
    }

    public function getDbBackup(): ?string
    {
        return $this->dbBackup;
    }
    public function setDbBackup(?string $dbBackup): static
    {
        $this->dbBackup = $dbBackup;

        return $this;
    }

    public function getFilesBackup(): ?string
    {
        return $this->filesBackup;
    }
    public function setFilesBackup(?string $filesBackup): static
    {
        $this->filesBackup = $filesBackup;

        return $this;
    }

    public function getFilesBackupSize(): ?int
    {
        return null === $this->filesBackupSize ? null : (int) $this->filesBackupSize;
    }
    public function setFilesBackupSize(?int $filesBackupSize): static
    {
        $this->filesBackupSize = $filesBackupSize;

        return $this;
    }

    public function getFilesBackupDeletedAt(): ?\DateTimeImmutable
    {
        return $this->filesBackupDeletedAt;
    }
    public function setFilesBackupDeletedAt(?\DateTimeImmutable $filesBackupDeletedAt): static
    {
        $this->filesBackupDeletedAt = $filesBackupDeletedAt;

        return $this;
    }

    /** La sauvegarde des fichiers existe encore et attend la validation de l'utilisateur pour être supprimée. */
    public function hasFilesBackup(): bool
    {
        return null !== $this->filesBackup && null === $this->filesBackupDeletedAt;
    }

    public function isRolledBack(): bool
    {
        return $this->rolledBack;
    }
    public function setRolledBack(bool $rolledBack): static
    {
        $this->rolledBack = $rolledBack;

        return $this;
    }
}

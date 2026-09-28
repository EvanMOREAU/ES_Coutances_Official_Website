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

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function isRunning(): bool { return $this->status === self::STATUS_RUNNING; }

    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }

    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static { $this->finishedAt = $finishedAt; return $this; }

    public function getFromCommit(): ?string { return $this->fromCommit; }
    public function setFromCommit(?string $fromCommit): static { $this->fromCommit = $fromCommit; return $this; }

    public function getToCommit(): ?string { return $this->toCommit; }
    public function setToCommit(?string $toCommit): static { $this->toCommit = $toCommit; return $this; }

    public function getTriggeredBy(): ?string { return $this->triggeredBy; }
    public function setTriggeredBy(?string $triggeredBy): static { $this->triggeredBy = $triggeredBy; return $this; }

    public function getStep(): ?string { return $this->step; }
    public function setStep(?string $step): static { $this->step = $step; return $this; }

    public function getLog(): string { return $this->log; }
    public function appendLog(string $text): static { $this->log .= $text; return $this; }

}

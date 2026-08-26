<?php

namespace App\Entity;

use App\Repository\ContactSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactSettingsRepository::class)]
class ContactSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 255)]
    private string $adresse = '';

    #[ORM\Column(length: 30)]
    private string $telephone = '';

    #[ORM\Column(length: 100)]
    private string $horaireLundi = '';

    #[ORM\Column(length: 100)]
    private string $horaireMardi = '';

    #[ORM\Column(length: 100)]
    private string $horaireMercredi = '';

    #[ORM\Column(length: 100)]
    private string $horaireJeudi = '';

    #[ORM\Column(length: 100)]
    private string $horaireVendredi = '';

    #[ORM\Column(length: 100)]
    private string $horaireSamedi = '';

    #[ORM\Column(length: 100)]
    private string $horaireDimanche = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresse(): string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getHoraireLundi(): string
    {
        return $this->horaireLundi;
    }

    public function setHoraireLundi(string $horaireLundi): static
    {
        $this->horaireLundi = $horaireLundi;

        return $this;
    }

    public function getHoraireMardi(): string
    {
        return $this->horaireMardi;
    }

    public function setHoraireMardi(string $horaireMardi): static
    {
        $this->horaireMardi = $horaireMardi;

        return $this;
    }

    public function getHoraireMercredi(): string
    {
        return $this->horaireMercredi;
    }

    public function setHoraireMercredi(string $horaireMercredi): static
    {
        $this->horaireMercredi = $horaireMercredi;

        return $this;
    }

    public function getHoraireJeudi(): string
    {
        return $this->horaireJeudi;
    }

    public function setHoraireJeudi(string $horaireJeudi): static
    {
        $this->horaireJeudi = $horaireJeudi;

        return $this;
    }

    public function getHoraireVendredi(): string
    {
        return $this->horaireVendredi;
    }

    public function setHoraireVendredi(string $horaireVendredi): static
    {
        $this->horaireVendredi = $horaireVendredi;

        return $this;
    }

    public function getHoraireSamedi(): string
    {
        return $this->horaireSamedi;
    }

    public function setHoraireSamedi(string $horaireSamedi): static
    {
        $this->horaireSamedi = $horaireSamedi;

        return $this;
    }

    public function getHoraireDimanche(): string
    {
        return $this->horaireDimanche;
    }

    public function setHoraireDimanche(string $horaireDimanche): static
    {
        $this->horaireDimanche = $horaireDimanche;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

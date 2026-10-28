<?php

namespace App\Entity;

use App\Repository\LocaleSettingRepository;
use Doctrine\ORM\Mapping as ORM;

/** Activation d'une langue de l'interface (une ligne par code de App\Service\Locale\Locales::AVAILABLE). */
#[ORM\Entity(repositoryClass: LocaleSettingRepository::class)]
class LocaleSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    private string $code;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function __construct(string $code, bool $enabled = true)
    {
        $this->code    = $code;
        $this->enabled = $enabled;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}

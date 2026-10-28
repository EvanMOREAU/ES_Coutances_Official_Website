<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Traduction automatique d'un texte français (clé : empreinte du texte + langue), calculée une seule fois. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_translation_hash_locale', columns: ['hash', 'locale'])]
class Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $hash = '';

    #[ORM\Column(length: 10)]
    private string $locale = '';

    #[ORM\Column(type: 'text')]
    private string $source = '';

    #[ORM\Column(type: 'text')]
    private string $translated = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTranslated(): string
    {
        return $this->translated;
    }
}

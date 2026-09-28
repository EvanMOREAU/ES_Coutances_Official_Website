<?php

namespace App\Entity\Concern;

use Doctrine\ORM\Mapping as ORM;

/**
 * Statut éditorial d'un enregistrement listé dans l'admin : actif, brouillon
 * ou archivé. Utilisé par les tableaux (onglets de filtre + actions groupées).
 */
trait StatutTrait
{
    public const STATUT_ACTIVE   = 'active';
    public const STATUT_DRAFT    = 'draft';
    public const STATUT_ARCHIVED = 'archived';

    public const STATUTS = [
        self::STATUT_ACTIVE   => 'Actif',
        self::STATUT_DRAFT    => 'Brouillon',
        self::STATUT_ARCHIVED => 'Archivé',
    ];

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private string $statut = self::STATUT_ACTIVE;

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!isset(self::STATUTS[$statut])) {
            throw new \InvalidArgumentException(sprintf('Statut inconnu "%s".', $statut));
        }

        $this->statut = $statut;

        return $this;
    }

    public function getStatutLabel(): string
    {
        return self::STATUTS[$this->statut];
    }
}

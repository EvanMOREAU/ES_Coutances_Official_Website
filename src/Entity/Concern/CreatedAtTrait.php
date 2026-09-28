<?php

namespace App\Entity\Concern;

use Doctrine\ORM\Mapping as ORM;

/**
 * Date de création, renseignée automatiquement à l'insertion. Reste nulle
 * pour les enregistrements antérieurs à l'introduction de ce champ.
 */
trait CreatedAtTrait
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Boutique : suivi du remboursement d'une commande payée puis annulée. */
final class Version20261012100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commandes : date et note de remboursement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD remboursee_le DATETIME DEFAULT NULL, ADD remboursement_note VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP remboursee_le, DROP remboursement_note');
    }
}

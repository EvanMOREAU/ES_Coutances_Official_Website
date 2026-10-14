<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Boutique : seuil d'alerte de stock bas (réglable). */
final class Version20261014101854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boutique : seuil d\'alerte de stock bas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boutique_settings ADD seuil_stock_bas INT DEFAULT 3 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boutique_settings DROP seuil_stock_bas');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928102100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table boutique_settings (mise en maintenance de la boutique).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE boutique_settings (id INT AUTO_INCREMENT NOT NULL, en_maintenance TINYINT NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE boutique_settings');
    }
}

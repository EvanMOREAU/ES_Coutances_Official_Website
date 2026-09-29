<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929031314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les paramètres HelloAsso (client_id/client_secret) pour le paiement en ligne.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE helloasso_settings (
              id INT AUTO_INCREMENT NOT NULL,
              actif TINYINT DEFAULT 0 NOT NULL,
              environnement VARCHAR(20) DEFAULT 'sandbox' NOT NULL,
              client_id VARCHAR(255) DEFAULT NULL,
              client_secret_chiffre LONGTEXT DEFAULT NULL,
              organisation_slug VARCHAR(255) DEFAULT NULL,
              updated_at DATETIME DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE helloasso_settings');
    }
}

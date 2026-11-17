<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Messages du formulaire de contact conservés en base et consultables dans l'admin. */
final class Version20261117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages du formulaire de contact : table es_contact_message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE es_contact_message (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(50) DEFAULT NULL, type VARCHAR(30) NOT NULL, sujet VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, archived TINYINT DEFAULT 0 NOT NULL, INDEX IDX_EEF64E4A8B8E8428 (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE es_contact_message');
    }
}

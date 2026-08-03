<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes the equipe table: the "Equipe" feature (entity, repository, admin
 * CRUD, nav dropdown) has been removed from the codebase.
 */
final class Version20260803130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop equipe table (feature removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE equipe');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, categorie VARCHAR(50) NOT NULL, niveau VARCHAR(100) NOT NULL, lien_fff VARCHAR(255) DEFAULT NULL, ordre INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}

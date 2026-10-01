<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930155718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute le partage (profils / rôles) des événements internes du planning.";
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entrainement ADD partage_profils JSON NOT NULL, ADD partage_roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entrainement DROP partage_profils, DROP partage_roles');
    }
}

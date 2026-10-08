<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Mises à jour : sauvegarde de la base et des fichiers prises avant chaque déploiement, retour arrière automatique. */
final class Version20261008100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Déploiements : sauvegardes avant mise à jour (base, fichiers) et indicateur de retour arrière';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deployment ADD db_backup VARCHAR(255) DEFAULT NULL, ADD files_backup VARCHAR(255) DEFAULT NULL, ADD files_backup_size BIGINT DEFAULT NULL, ADD files_backup_deleted_at DATETIME DEFAULT NULL, ADD rolled_back TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deployment DROP db_backup, DROP files_backup, DROP files_backup_size, DROP files_backup_deleted_at, DROP rolled_back');
    }
}

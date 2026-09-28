<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tableaux de l'admin : statut (actif / brouillon / archivé) et date de
 * création sur les entités listées, préférences d'affichage par utilisateur.
 */
final class Version20260921140000 extends AbstractMigration
{
    private const TABLES = ['famille', 'licencie', 'equipe', 'saison', 'partenaire'];

    public function getDescription(): string
    {
        return "Statut et date de création sur famille/licencie/equipe/saison/partenaire ; préférences de tableaux sur user";
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf("ALTER TABLE %s ADD created_at DATETIME DEFAULT NULL, ADD statut VARCHAR(20) DEFAULT 'active' NOT NULL", $table));
        }

        // Les enregistrements auparavant "inactifs" deviennent archivés.
        foreach (['licencie', 'equipe', 'partenaire'] as $table) {
            $this->addSql(sprintf("UPDATE %s SET statut = 'archived' WHERE actif = 0", $table));
        }

        $this->addSql('ALTER TABLE `user` ADD table_preferences JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP created_at, DROP statut', $table));
        }

        $this->addSql('ALTER TABLE `user` DROP table_preferences');
    }
}

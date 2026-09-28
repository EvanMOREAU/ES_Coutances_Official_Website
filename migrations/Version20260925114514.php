<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 */
final class Version20260925114514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changelog lu depuis un fichier : suppression des tables release et du lien deployment.release';
    }

    public function up(Schema $schema): void
    {
        // Les noms des clés étrangères varient selon l'historique de la base : on les relit.
        $fks = $this->connection->fetchFirstColumn(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment' AND COLUMN_NAME = 'release_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        foreach ($fks as $fk) {
            $this->addSql(sprintf('ALTER TABLE deployment DROP FOREIGN KEY `%s`', $fk));
        }
        $indexes = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment' AND COLUMN_NAME = 'release_id'"
        );
        foreach ($indexes as $index) {
            $this->addSql(sprintf('DROP INDEX `%s` ON deployment', $index));
        }
        $this->addSql('ALTER TABLE deployment DROP release_id');
        $this->addSql('DROP TABLE IF EXISTS release_entry');
        $this->addSql('DROP TABLE IF EXISTS `release`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `release` (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, title VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, released_at DATETIME NOT NULL, commit_hash VARCHAR(40) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE release_entry (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, description LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, position INT NOT NULL, release_id INT NOT NULL, INDEX IDX_A8BFEF07B12A727D (release_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE release_entry ADD CONSTRAINT `FK_A8BFEF07B12A727D` FOREIGN KEY (release_id) REFERENCES `release` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deployment ADD release_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE deployment ADD CONSTRAINT `FK_EB1255BEB12A727D` FOREIGN KEY (release_id) REFERENCES `release` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_EB1255BEB12A727D ON deployment (release_id)');
    }
}

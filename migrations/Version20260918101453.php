<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918101453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gestion des licenciés et des familles : ajout des tables saison, famille, licencie et licencie_categorie.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE famille (
              id INT AUTO_INCREMENT NOT NULL,
              nom VARCHAR(150) NOT NULL,
              adresse VARCHAR(255) DEFAULT NULL,
              code_postal VARCHAR(10) DEFAULT NULL,
              ville VARCHAR(100) DEFAULT NULL,
              telephone VARCHAR(20) DEFAULT NULL,
              user_id INT NOT NULL,
              UNIQUE INDEX UNIQ_2473F213A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE licencie (
              id INT AUTO_INCREMENT NOT NULL,
              nom VARCHAR(100) NOT NULL,
              prenom VARCHAR(100) NOT NULL,
              date_naissance DATETIME NOT NULL,
              actif TINYINT NOT NULL,
              certificat_medical_name VARCHAR(255) DEFAULT NULL,
              autorisation_parentale_name VARCHAR(255) DEFAULT NULL,
              updated_at DATETIME DEFAULT NULL,
              famille_id INT NOT NULL,
              user_id INT NOT NULL,
              saison_id INT NOT NULL,
              INDEX IDX_3B75561297A77B84 (famille_id),
              UNIQUE INDEX UNIQ_3B755612A76ED395 (user_id),
              INDEX IDX_3B755612F965414C (saison_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE licencie_categorie (
              licencie_id INT NOT NULL,
              categorie_id INT NOT NULL,
              INDEX IDX_6826705CB56DCD74 (licencie_id),
              INDEX IDX_6826705CBCF5E72D (categorie_id),
              PRIMARY KEY (licencie_id, categorie_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE saison (
              id INT AUTO_INCREMENT NOT NULL,
              libelle VARCHAR(20) NOT NULL,
              date_debut DATETIME NOT NULL,
              date_fin DATETIME NOT NULL,
              active TINYINT NOT NULL,
              UNIQUE INDEX UNIQ_C0D0D586A4D60759 (libelle),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              famille
            ADD
              CONSTRAINT FK_2473F213A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie
            ADD
              CONSTRAINT FK_3B75561297A77B84 FOREIGN KEY (famille_id) REFERENCES famille (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie
            ADD
              CONSTRAINT FK_3B755612A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie
            ADD
              CONSTRAINT FK_3B755612F965414C FOREIGN KEY (saison_id) REFERENCES saison (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie_categorie
            ADD
              CONSTRAINT FK_6826705CB56DCD74 FOREIGN KEY (licencie_id) REFERENCES licencie (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie_categorie
            ADD
              CONSTRAINT FK_6826705CBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE famille DROP FOREIGN KEY FK_2473F213A76ED395');
        $this->addSql('ALTER TABLE licencie DROP FOREIGN KEY FK_3B75561297A77B84');
        $this->addSql('ALTER TABLE licencie DROP FOREIGN KEY FK_3B755612A76ED395');
        $this->addSql('ALTER TABLE licencie DROP FOREIGN KEY FK_3B755612F965414C');
        $this->addSql('ALTER TABLE licencie_categorie DROP FOREIGN KEY FK_6826705CB56DCD74');
        $this->addSql('ALTER TABLE licencie_categorie DROP FOREIGN KEY FK_6826705CBCF5E72D');
        $this->addSql('DROP TABLE famille');
        $this->addSql('DROP TABLE licencie');
        $this->addSql('DROP TABLE licencie_categorie');
        $this->addSql('DROP TABLE saison');
    }
}

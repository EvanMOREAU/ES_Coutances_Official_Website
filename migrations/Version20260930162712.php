<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930162712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Partenaires/Sponsors étendu : contrats par partenaire (règlements échelonnés, tâches à réaliser, documents hébergés).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE contrat_partenaire (
              id INT AUTO_INCREMENT NOT NULL,
              titre VARCHAR(150) NOT NULL,
              montant_centimes INT NOT NULL,
              date_debut DATE DEFAULT NULL,
              date_fin DATE DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              partenaire_id INT NOT NULL,
              INDEX IDX_8FC9561898DE13AC (partenaire_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contrat_partenaire_document (
              id INT AUTO_INCREMENT NOT NULL,
              fichier_name VARCHAR(255) DEFAULT NULL,
              nom_original VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              contrat_id INT NOT NULL,
              INDEX IDX_C49BA96B1823061F (contrat_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contrat_partenaire_reglement (
              id INT AUTO_INCREMENT NOT NULL,
              mode VARCHAR(20) NOT NULL,
              montant_centimes INT NOT NULL,
              ordre INT DEFAULT 1 NOT NULL,
              date_echeance DATE DEFAULT NULL,
              recu TINYINT DEFAULT 0 NOT NULL,
              date_remise DATE DEFAULT NULL,
              reference VARCHAR(100) DEFAULT NULL,
              contrat_id INT NOT NULL,
              INDEX IDX_88FE5FB61823061F (contrat_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contrat_partenaire_tache (
              id INT AUTO_INCREMENT NOT NULL,
              titre VARCHAR(150) NOT NULL,
              echeance DATE DEFAULT NULL,
              fait TINYINT DEFAULT 0 NOT NULL,
              ordre INT DEFAULT 0 NOT NULL,
              contrat_id INT NOT NULL,
              INDEX IDX_A0458B451823061F (contrat_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              contrat_partenaire
            ADD
              CONSTRAINT FK_8FC9561898DE13AC FOREIGN KEY (partenaire_id) REFERENCES partenaire (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              contrat_partenaire_document
            ADD
              CONSTRAINT FK_C49BA96B1823061F FOREIGN KEY (contrat_id) REFERENCES contrat_partenaire (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              contrat_partenaire_reglement
            ADD
              CONSTRAINT FK_88FE5FB61823061F FOREIGN KEY (contrat_id) REFERENCES contrat_partenaire (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              contrat_partenaire_tache
            ADD
              CONSTRAINT FK_A0458B451823061F FOREIGN KEY (contrat_id) REFERENCES contrat_partenaire (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contrat_partenaire DROP FOREIGN KEY FK_8FC9561898DE13AC');
        $this->addSql('ALTER TABLE contrat_partenaire_document DROP FOREIGN KEY FK_C49BA96B1823061F');
        $this->addSql('ALTER TABLE contrat_partenaire_reglement DROP FOREIGN KEY FK_88FE5FB61823061F');
        $this->addSql('ALTER TABLE contrat_partenaire_tache DROP FOREIGN KEY FK_A0458B451823061F');
        $this->addSql('DROP TABLE contrat_partenaire');
        $this->addSql('DROP TABLE contrat_partenaire_document');
        $this->addSql('DROP TABLE contrat_partenaire_reglement');
        $this->addSql('DROP TABLE contrat_partenaire_tache');
    }
}

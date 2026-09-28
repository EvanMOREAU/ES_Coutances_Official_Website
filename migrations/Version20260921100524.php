<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921100524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catégories automatiques : équipes, décalage de catégorie des licenciés, prénom et bio des utilisateurs.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE equipe (
              id INT AUTO_INCREMENT NOT NULL,
              nom VARCHAR(100) NOT NULL,
              categorie VARCHAR(20) NOT NULL,
              actif TINYINT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE licencie_equipe (
              licencie_id INT NOT NULL,
              equipe_id INT NOT NULL,
              INDEX IDX_E1F42B84B56DCD74 (licencie_id),
              INDEX IDX_E1F42B846D861B89 (equipe_id),
              PRIMARY KEY (licencie_id, equipe_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie_equipe
            ADD
              CONSTRAINT FK_E1F42B84B56DCD74 FOREIGN KEY (licencie_id) REFERENCES licencie (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie_equipe
            ADD
              CONSTRAINT FK_E1F42B846D861B89 FOREIGN KEY (equipe_id) REFERENCES equipe (id) ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE licencie_categorie DROP FOREIGN KEY `FK_6826705CB56DCD74`');
        $this->addSql('ALTER TABLE licencie_categorie DROP FOREIGN KEY `FK_6826705CBCF5E72D`');
        $this->addSql('DROP TABLE licencie_categorie');
        $this->addSql('ALTER TABLE licencie ADD decalage_categorie INT DEFAULT 0 NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user
            ADD
              prenom VARCHAR(100) DEFAULT NULL,
            ADD
              bio LONGTEXT DEFAULT NULL,
            CHANGE
              theme theme VARCHAR(20) NOT NULL,
            CHANGE
              color_scheme color_scheme VARCHAR(20) NOT NULL,
            CHANGE
              density density VARCHAR(20) NOT NULL,
            CHANGE
              notification_preferences notification_preferences JSON NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE licencie_categorie (
              licencie_id INT NOT NULL,
              categorie_id INT NOT NULL,
              INDEX IDX_6826705CB56DCD74 (licencie_id),
              INDEX IDX_6826705CBCF5E72D (categorie_id),
              PRIMARY KEY (licencie_id, categorie_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie_categorie
            ADD
              CONSTRAINT `FK_6826705CB56DCD74` FOREIGN KEY (licencie_id) REFERENCES licencie (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              licencie_categorie
            ADD
              CONSTRAINT `FK_6826705CBCF5E72D` FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE licencie_equipe DROP FOREIGN KEY FK_E1F42B84B56DCD74');
        $this->addSql('ALTER TABLE licencie_equipe DROP FOREIGN KEY FK_E1F42B846D861B89');
        $this->addSql('DROP TABLE equipe');
        $this->addSql('DROP TABLE licencie_equipe');
        $this->addSql('ALTER TABLE licencie DROP decalage_categorie');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              `user`
            DROP
              prenom,
            DROP
              bio,
            CHANGE
              theme theme VARCHAR(20) DEFAULT 'dark' NOT NULL,
            CHANGE
              color_scheme color_scheme VARCHAR(20) DEFAULT 'violet' NOT NULL,
            CHANGE
              density density VARCHAR(20) DEFAULT 'comfortable' NOT NULL,
            CHANGE
              notification_preferences notification_preferences JSON DEFAULT '["new_famille", "missing_documents"]' NOT NULL
        SQL);
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730141332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, ordre INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE membre_categorie (membre_id INT NOT NULL, categorie_id INT NOT NULL, INDEX IDX_F618D5CA6A99F74A (membre_id), INDEX IDX_F618D5CABCF5E72D (categorie_id), PRIMARY KEY (membre_id, categorie_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE membre_categorie ADD CONSTRAINT FK_F618D5CA6A99F74A FOREIGN KEY (membre_id) REFERENCES membre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre_categorie ADD CONSTRAINT FK_F618D5CABCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre DROP FOREIGN KEY `FK_F6B4FB29727ACA70`');
        $this->addSql('DROP INDEX IDX_F6B4FB29727ACA70 ON membre');
        $this->addSql('ALTER TABLE membre ADD diplome VARCHAR(150) DEFAULT NULL, DROP parent_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE membre_categorie DROP FOREIGN KEY FK_F618D5CA6A99F74A');
        $this->addSql('ALTER TABLE membre_categorie DROP FOREIGN KEY FK_F618D5CABCF5E72D');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE membre_categorie');
        $this->addSql('ALTER TABLE membre ADD parent_id INT DEFAULT NULL, DROP diplome');
        $this->addSql('ALTER TABLE membre ADD CONSTRAINT `FK_F6B4FB29727ACA70` FOREIGN KEY (parent_id) REFERENCES membre (id)');
        $this->addSql('CREATE INDEX IDX_F6B4FB29727ACA70 ON membre (parent_id)');
    }
}

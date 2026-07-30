<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721111753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE album (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, categorie VARCHAR(50) NOT NULL, lien_externe VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, publie TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE equipe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, categorie VARCHAR(50) NOT NULL, niveau VARCHAR(100) NOT NULL, lien_fff VARCHAR(255) DEFAULT NULL, ordre INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offre_emploi (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, type VARCHAR(50) NOT NULL, actif TINYINT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE partenaire (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, logo_name VARCHAR(255) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, niveau VARCHAR(50) NOT NULL, actif TINYINT NOT NULL, ordre INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE photo (id INT AUTO_INCREMENT NOT NULL, image_name VARCHAR(255) NOT NULL, legende VARCHAR(255) DEFAULT NULL, album_id INT NOT NULL, INDEX IDX_14B784181137ABCF (album_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE slide_carousel (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, titre_mot VARCHAR(100) DEFAULT NULL, sous_titre VARCHAR(255) DEFAULT NULL, image_name VARCHAR(255) NOT NULL, ordre INT NOT NULL, actif TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181137ABCF FOREIGN KEY (album_id) REFERENCES album (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784181137ABCF');
        $this->addSql('DROP TABLE album');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE equipe');
        $this->addSql('DROP TABLE offre_emploi');
        $this->addSql('DROP TABLE partenaire');
        $this->addSql('DROP TABLE photo');
        $this->addSql('DROP TABLE slide_carousel');
    }
}

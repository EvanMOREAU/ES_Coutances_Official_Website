<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes the legacy album/article/photo tables, orphaned since their
 * entities and repositories were dropped from the codebase.
 */
final class Version20260803120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy album, article and photo tables (unused, entities removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784181137ABCF');
        $this->addSql('DROP TABLE photo');
        $this->addSql('DROP TABLE album');
        $this->addSql('DROP TABLE article');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE album (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, categorie VARCHAR(50) NOT NULL, lien_externe VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, publie TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE photo (id INT AUTO_INCREMENT NOT NULL, image_name VARCHAR(255) NOT NULL, legende VARCHAR(255) DEFAULT NULL, album_id INT NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_14B784181137ABCF (album_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181137ABCF FOREIGN KEY (album_id) REFERENCES album (id)');
    }
}

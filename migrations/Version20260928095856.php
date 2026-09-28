<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928095856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les catégories boutique (CategorieArticle) et la relation sur Article.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categorie_article (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, ordre INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_5DB9A0C4989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE article ADD categorie_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_article (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_23A0E66BCF5E72D ON article (categorie_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66BCF5E72D');
        $this->addSql('DROP INDEX IDX_23A0E66BCF5E72D ON article');
        $this->addSql('ALTER TABLE article DROP categorie_id');
        $this->addSql('DROP TABLE categorie_article');
    }
}

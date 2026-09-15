<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace le bloc "chiffres clés" de la page d'accueil par une bannière
 * image + lien (homepage_banner), et structure les "Pages de contenu"
 * (Histoire, Infrastructure) avec une image d'en-tête et un chapô.
 */
final class Version20260915120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace chiffres_cles with homepage_banner (image+link), add image/chapo to page_contenu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE chiffres_cles');
        $this->addSql('CREATE TABLE homepage_banner (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, actif TINYINT NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE page_contenu ADD chapo LONGTEXT DEFAULT NULL, ADD image_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_contenu DROP chapo, DROP image_name');
        $this->addSql('DROP TABLE homepage_banner');
        $this->addSql('CREATE TABLE chiffres_cles (id INT AUTO_INCREMENT NOT NULL, nb_licencies INT NOT NULL, nb_educateurs INT NOT NULL, nb_benevoles INT NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}

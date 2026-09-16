<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916094426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du système de blocs de contenu (texte/image) pour les pages, et lien optionnel vers une page de détail depuis les cartes "Nous rejoindre"';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE page_bloc (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, texte LONGTEXT DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, legende VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, ordre INT NOT NULL, page_contenu_id INT NOT NULL, INDEX IDX_40BC898037FCF516 (page_contenu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE page_bloc ADD CONSTRAINT FK_40BC898037FCF516 FOREIGN KEY (page_contenu_id) REFERENCES page_contenu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page_contenu CHANGE contenu contenu LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE rejoindre_card ADD page_detail_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rejoindre_card ADD CONSTRAINT FK_8B001A06E33EBC74 FOREIGN KEY (page_detail_id) REFERENCES page_contenu (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8B001A06E33EBC74 ON rejoindre_card (page_detail_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE page_bloc DROP FOREIGN KEY FK_40BC898037FCF516');
        $this->addSql('DROP TABLE page_bloc');
        $this->addSql('ALTER TABLE page_contenu CHANGE contenu contenu LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE rejoindre_card DROP FOREIGN KEY FK_8B001A06E33EBC74');
        $this->addSql('DROP INDEX IDX_8B001A06E33EBC74 ON rejoindre_card');
        $this->addSql('ALTER TABLE rejoindre_card DROP page_detail_id');
    }
}

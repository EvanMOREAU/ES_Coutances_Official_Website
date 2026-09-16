<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916102356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retrait du système de blocs de contenu séparé (page_bloc) : le contenu des pages repasse par un unique éditeur riche (Trix) avec insertion d\'images inline.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE page_bloc DROP FOREIGN KEY `FK_40BC898037FCF516`');
        $this->addSql('DROP TABLE page_bloc');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE page_bloc (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, texte LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, image_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, legende VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, updated_at DATETIME DEFAULT NULL, ordre INT NOT NULL, page_contenu_id INT NOT NULL, INDEX IDX_40BC898037FCF516 (page_contenu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE page_bloc ADD CONSTRAINT `FK_40BC898037FCF516` FOREIGN KEY (page_contenu_id) REFERENCES page_contenu (id) ON DELETE CASCADE');
    }
}

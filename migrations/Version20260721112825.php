<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721112825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE offre_emploi ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE partenaire ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE slide_carousel ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP updated_at');
        $this->addSql('ALTER TABLE offre_emploi DROP updated_at');
        $this->addSql('ALTER TABLE partenaire DROP updated_at');
        $this->addSql('ALTER TABLE photo DROP updated_at');
        $this->addSql('ALTER TABLE slide_carousel DROP updated_at');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005103924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation ADD staff_notified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_participant ADD last_notified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE match_live ADD debut_at DATETIME DEFAULT NULL, ADD fin_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP staff_notified_at');
        $this->addSql('ALTER TABLE conversation_participant DROP last_notified_at');
        $this->addSql('ALTER TABLE match_live DROP debut_at, DROP fin_at');
    }
}

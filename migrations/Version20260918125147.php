<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918125147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajout de l'avatar et des préférences d'apparence/notifications sur User.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user
            ADD
              avatar_name VARCHAR(255) DEFAULT NULL,
            ADD
              updated_at DATETIME DEFAULT NULL,
            ADD
              theme VARCHAR(20) NOT NULL DEFAULT 'dark',
            ADD
              color_scheme VARCHAR(20) NOT NULL DEFAULT 'violet',
            ADD
              density VARCHAR(20) NOT NULL DEFAULT 'comfortable',
            ADD
              notification_preferences JSON NOT NULL DEFAULT ('["new_famille", "missing_documents"]')
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE
              `user`
            DROP
              avatar_name,
            DROP
              updated_at,
            DROP
              theme,
            DROP
              color_scheme,
            DROP
              density,
            DROP
              notification_preferences
        SQL);
    }
}

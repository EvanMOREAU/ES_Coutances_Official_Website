<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005100741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_attachment (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, size INT NOT NULL, temporary TINYINT NOT NULL, expires_at DATETIME NOT NULL, purged_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, message_id INT NOT NULL, owner_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BEAE9C1D537A1329 (message_id), INDEX IDX_BEAE9C1D7E3C61F9 (owner_id), INDEX idx_chat_attachment_expiry (expires_at, purged_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_attachment ADD CONSTRAINT FK_BEAE9C1D537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_attachment ADD CONSTRAINT FK_BEAE9C1D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_attachment DROP FOREIGN KEY FK_BEAE9C1D537A1329');
        $this->addSql('ALTER TABLE chat_attachment DROP FOREIGN KEY FK_BEAE9C1D7E3C61F9');
        $this->addSql('DROP TABLE chat_attachment');
    }
}

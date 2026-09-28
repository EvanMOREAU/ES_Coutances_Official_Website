<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Changelog et déploiements, gestionnaire de fichiers (favoris), messagerie
 * (discussions, participants, messages) et présence des utilisateurs.
 */
final class Version20260921115825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changelog, déploiements, favoris de fichiers, messagerie et dernière activité des utilisateurs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(120) DEFAULT NULL, is_group TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_conversation_updated (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, last_read_message_id INT NOT NULL, conversation_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), UNIQUE INDEX uniq_conversation_user (conversation_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deployment (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, from_commit VARCHAR(40) DEFAULT NULL, to_commit VARCHAR(40) DEFAULT NULL, triggered_by VARCHAR(100) DEFAULT NULL, step VARCHAR(100) DEFAULT NULL, log LONGTEXT NOT NULL, release_id INT DEFAULT NULL, INDEX IDX_EB1255BEB12A727D (release_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE file_favorite (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(500) NOT NULL, user_id INT NOT NULL, INDEX IDX_D9950144A76ED395 (user_id), UNIQUE INDEX uniq_file_favorite (user_id, path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307FF675F31B (author_id), INDEX idx_message_conversation (conversation_id, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `release` (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(30) NOT NULL, title VARCHAR(150) DEFAULT NULL, status VARCHAR(20) NOT NULL, released_at DATETIME NOT NULL, commit_hash VARCHAR(40) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE release_entry (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, description LONGTEXT NOT NULL, position INT NOT NULL, release_id INT NOT NULL, INDEX IDX_A8BFEF07B12A727D (release_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deployment ADD CONSTRAINT FK_EB1255BEB12A727D FOREIGN KEY (release_id) REFERENCES `release` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE file_favorite ADD CONSTRAINT FK_D9950144A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE release_entry ADD CONSTRAINT FK_A8BFEF07B12A727D FOREIGN KEY (release_id) REFERENCES `release` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD last_seen_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('ALTER TABLE deployment DROP FOREIGN KEY FK_EB1255BEB12A727D');
        $this->addSql('ALTER TABLE file_favorite DROP FOREIGN KEY FK_D9950144A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF675F31B');
        $this->addSql('ALTER TABLE release_entry DROP FOREIGN KEY FK_A8BFEF07B12A727D');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('DROP TABLE deployment');
        $this->addSql('DROP TABLE file_favorite');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE `release`');
        $this->addSql('DROP TABLE release_entry');
        $this->addSql('ALTER TABLE `user` DROP last_seen_at');
    }
}

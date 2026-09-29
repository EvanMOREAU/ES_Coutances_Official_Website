<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929130131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le stockage des clés d\'accès (passkeys / WebAuthn), en cours de mise en place.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE webauthn_credential (
              id INT AUTO_INCREMENT NOT NULL,
              credential_id VARCHAR(255) NOT NULL,
              donnees LONGTEXT NOT NULL,
              label VARCHAR(100) NOT NULL,
              created_at DATETIME NOT NULL,
              last_used_at DATETIME DEFAULT NULL,
              user_id INT NOT NULL,
              UNIQUE INDEX UNIQ_850123F92558A7A5 (credential_id),
              INDEX IDX_850123F9A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              webauthn_credential
            ADD
              CONSTRAINT FK_850123F9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
        $this->addSql('ALTER TABLE `user` ADD webauthn_user_handle VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webauthn_credential DROP FOREIGN KEY FK_850123F9A76ED395');
        $this->addSql('DROP TABLE webauthn_credential');
        $this->addSql('ALTER TABLE `user` DROP webauthn_user_handle');
    }
}

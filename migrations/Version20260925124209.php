<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 */
final class Version20260925124209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal d activite et parametres e-mail (SMTP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log (id BIGINT AUTO_INCREMENT NOT NULL, occurred_at DATETIME NOT NULL, type VARCHAR(20) NOT NULL, operation VARCHAR(30) DEFAULT NULL, category VARCHAR(60) NOT NULL, summary VARCHAR(500) NOT NULL, entity_class VARCHAR(190) DEFAULT NULL, entity_id VARCHAR(40) DEFAULT NULL, entity_label VARCHAR(255) DEFAULT NULL, changes JSON DEFAULT NULL, context JSON DEFAULT NULL, user_id INT DEFAULT NULL, user_email VARCHAR(180) DEFAULT NULL, user_name VARCHAR(200) DEFAULT NULL, user_roles VARCHAR(200) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, forwarded_for VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, route VARCHAR(120) DEFAULT NULL, path VARCHAR(500) DEFAULT NULL, status_code INT DEFAULT NULL, request_id VARCHAR(32) DEFAULT NULL, source VARCHAR(10) DEFAULT \'web\' NOT NULL, INDEX IDX_AUDIT_DATE (occurred_at), INDEX IDX_AUDIT_USER (user_id), INDEX IDX_AUDIT_TYPE (type, category), INDEX IDX_AUDIT_REQUEST (request_id), INDEX IDX_AUDIT_ENTITY (entity_class, entity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mail_settings (id INT AUTO_INCREMENT NOT NULL, actif TINYINT DEFAULT 0 NOT NULL, host VARCHAR(255) DEFAULT NULL, port INT DEFAULT 587 NOT NULL, chiffrement VARCHAR(10) DEFAULT \'tls\' NOT NULL, username VARCHAR(255) DEFAULT NULL, password_chiffre LONGTEXT DEFAULT NULL, verifier_certificat TINYINT DEFAULT 1 NOT NULL, expediteur_adresse VARCHAR(180) DEFAULT NULL, expediteur_nom VARCHAR(120) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE mail_settings');
    }
}

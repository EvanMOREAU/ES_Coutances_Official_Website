<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les colonnes nécessaires à l'authentification à deux facteurs du back-office
 * (application TOTP, code par e-mail, codes de secours).
 */
final class Version20260929014939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les colonnes de double authentification (TOTP, e-mail, codes de secours) sur `user`.';
    }

    public function up(Schema $schema): void
    {
        // La colonne JSON ne peut pas porter de DEFAULT '[]' sur MariaDB : on l'ajoute nullable puis
        // on initialise chaque ligne existante avant de la contraindre en NOT NULL.
        $this->addSql('ALTER TABLE `user` ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD email_auth_code VARCHAR(20) DEFAULT NULL, ADD email_auth_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD backup_codes JSON DEFAULT NULL');
        $this->addSql("UPDATE `user` SET backup_codes = '[]' WHERE backup_codes IS NULL");
        $this->addSql('ALTER TABLE `user` MODIFY backup_codes JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP totp_secret, DROP email_auth_code, DROP email_auth_enabled, DROP backup_codes');
    }
}

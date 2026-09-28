<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 */
final class Version20260925112436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planning : type et equipes ; suppression des documents des licencies ; cascade sur les demandes de mot de passe ; couleur par defaut';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE entrainement ADD type VARCHAR(20) DEFAULT 'entrainement' NOT NULL, ADD equipes JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE entrainement ALTER equipes DROP DEFAULT');
        // Rouge du club : nouvelle couleur par défaut, appliquée aux comptes qui avaient encore l ancien défaut (violet).
        $this->addSql("UPDATE `user` SET color_scheme = 'rouge' WHERE color_scheme = 'violet'");
        $this->addSql('ALTER TABLE licencie DROP certificat_medical_name, DROP autorisation_parentale_name');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY `FK_7CE748AA76ED395`');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entrainement DROP type, DROP equipes');
        $this->addSql('ALTER TABLE licencie ADD certificat_medical_name VARCHAR(255) DEFAULT NULL, ADD autorisation_parentale_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT `FK_7CE748AA76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}

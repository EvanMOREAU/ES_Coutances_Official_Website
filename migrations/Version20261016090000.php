<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Langue de l'interface choisie par chaque compte (fr, en, de). */
final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Utilisateur : langue de l\'interface';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD locale VARCHAR(5) DEFAULT 'fr' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP locale');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Les comptes hors équipe (familles, licenciés, clients) gardent l'apparence claire qu'ils avaient avant l'ouverture des réglages d'apparence. */
final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Thème clair pour les comptes hors équipe existants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE `user` SET theme = 'light' WHERE theme = 'dark' AND roles NOT LIKE '%ROLE_EDITOR%' AND roles NOT LIKE '%ROLE_ADMIN%' AND roles NOT LIKE '%ROLE_DEV%'");
    }

    public function down(Schema $schema): void
    {
    }
}

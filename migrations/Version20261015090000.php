<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Planning : index des séances d'une série (modification / suppression d'une série à partir d'une date). */
final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planning : index (série, date) sur les entraînements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_ENTRAINEMENT_SERIE_DATE ON entrainement (serie, date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_ENTRAINEMENT_SERIE_DATE ON entrainement');
    }
}

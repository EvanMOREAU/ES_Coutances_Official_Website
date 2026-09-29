<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normalise en majuscules le nom et la ville des familles déjà en base (les nouveaux imports et
 * la saisie manuelle sont désormais normalisés au niveau de l'entité Famille).
 */
final class Version20260928143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Met en majuscules le nom et la ville des familles déjà enregistrées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE famille SET nom = UPPER(nom) WHERE nom IS NOT NULL');
        $this->addSql('UPDATE famille SET ville = UPPER(ville) WHERE ville IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Transformation de données non réversible (la casse d'origine n'est pas conservée).
    }
}

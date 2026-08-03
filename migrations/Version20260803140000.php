<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the chiffres_cles table: a single-row entity holding the editable
 * homepage stats (licenciés, éducateurs, bénévoles), mirroring the
 * PageContenu pattern used for Histoire/Infrastructure.
 */
final class Version20260803140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create chiffres_cles table for editable homepage stats';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE chiffres_cles (id INT AUTO_INCREMENT NOT NULL, nb_licencies INT NOT NULL, nb_educateurs INT NOT NULL, nb_benevoles INT NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO chiffres_cles (nb_licencies, nb_educateurs, nb_benevoles, updated_at) VALUES (500, 40, 80, NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE chiffres_cles');
    }
}

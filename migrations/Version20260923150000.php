<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Champs pour l'import des exports "Foot Club" (logiciel FFF) : identifiants
 * FFF et données complémentaires sur licencie, civilité et second
 * représentant légal sur famille.
 */
final class Version20260923150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Champs d'import FFF (Foot Club) sur licencie et famille";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE licencie
            ADD numero_personne VARCHAR(30) DEFAULT NULL,
            ADD numero_licence VARCHAR(30) DEFAULT NULL,
            ADD civilite VARCHAR(20) DEFAULT NULL,
            ADD lieu_naissance VARCHAR(150) DEFAULT NULL,
            ADD sexe VARCHAR(1) DEFAULT NULL,
            ADD nationalite VARCHAR(100) DEFAULT NULL,
            ADD type_licence VARCHAR(100) DEFAULT NULL,
            ADD code_categorie VARCHAR(20) DEFAULT NULL,
            ADD telephone VARCHAR(20) DEFAULT NULL,
            ADD email_individuel VARCHAR(180) DEFAULT NULL');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_LICENCIE_NUMERO_PERSONNE ON licencie (numero_personne)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_LICENCIE_NUMERO_LICENCE ON licencie (numero_licence)');

        $this->addSql('ALTER TABLE famille
            ADD civilite VARCHAR(20) DEFAULT NULL,
            ADD nom_repr_legal2 VARCHAR(150) DEFAULT NULL,
            ADD telephone_repr_legal2 VARCHAR(20) DEFAULT NULL,
            ADD email_repr_legal2 VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_LICENCIE_NUMERO_PERSONNE ON licencie');
        $this->addSql('DROP INDEX UNIQ_LICENCIE_NUMERO_LICENCE ON licencie');

        $this->addSql('ALTER TABLE licencie
            DROP numero_personne,
            DROP numero_licence,
            DROP civilite,
            DROP lieu_naissance,
            DROP sexe,
            DROP nationalite,
            DROP type_licence,
            DROP code_categorie,
            DROP telephone,
            DROP email_individuel');

        $this->addSql('ALTER TABLE famille
            DROP civilite,
            DROP nom_repr_legal2,
            DROP telephone_repr_legal2,
            DROP email_repr_legal2');
    }
}

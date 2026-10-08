<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Conformité : preuve d'acceptation des CGV, numéro de facture, consentement du compte, droit à l'image et autorisation parentale. */
final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consentements (commande, compte), numéro de facture, droit à l\'image et autorisation parentale des licenciés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD numero_facture VARCHAR(20) DEFAULT NULL, ADD cgv_accepted_at DATETIME DEFAULT NULL, ADD cgv_version VARCHAR(20) DEFAULT NULL, ADD cgv_accepted_ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D38D27AB1 ON commande (numero_facture)');
        $this->addSql('ALTER TABLE `user` ADD consent_at DATETIME DEFAULT NULL, ADD consent_version VARCHAR(20) DEFAULT NULL, ADD last_login_at DATETIME DEFAULT NULL');
        // Point de départ de la durée d'inactivité pour les comptes existants.
        $this->addSql('UPDATE `user` SET last_login_at = NOW()');
        $this->addSql('ALTER TABLE licencie ADD droit_image TINYINT DEFAULT NULL, ADD droit_image_at DATETIME DEFAULT NULL, ADD autorisation_parentale_at DATETIME DEFAULT NULL');

        // Les commandes déjà encaissées reçoivent leur numéro, dans l'ordre d'encaissement (séquence par année, sans trou).
        $this->addSql(<<<'SQL'
            UPDATE commande c
            JOIN (
                SELECT id, CONCAT('FAC-', YEAR(payee_le), '-', LPAD(ROW_NUMBER() OVER (PARTITION BY YEAR(payee_le) ORDER BY payee_le, id), 5, '0')) AS numero
                FROM commande
                WHERE payee_le IS NOT NULL AND statut <> 'annulee'
            ) n ON n.id = c.id
            SET c.numero_facture = n.numero
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6EEAA67D38D27AB1 ON commande');
        $this->addSql('ALTER TABLE commande DROP numero_facture, DROP cgv_accepted_at, DROP cgv_version, DROP cgv_accepted_ip');
        $this->addSql('ALTER TABLE `user` DROP consent_at, DROP consent_version, DROP last_login_at');
        $this->addSql('ALTER TABLE licencie DROP droit_image, DROP droit_image_at, DROP autorisation_parentale_at');
    }
}

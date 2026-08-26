<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs "page de contact" (adresse, téléphone, horaires) à
 * contact_settings, en pré-remplissant la ligne existante avec les valeurs
 * actuellement codées en dur dans templates/contact/index.html.twig, pour
 * ne rien changer visuellement tant que l'admin ne les modifie pas.
 */
final class Version20260826204135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute adresse/téléphone/horaires à contact_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE contact_settings
            ADD adresse VARCHAR(255) NOT NULL DEFAULT 'Stade Paul-Maundrell, BP 602, 50200 Coutances',
            ADD telephone VARCHAR(30) NOT NULL DEFAULT '02 33 47 04 90',
            ADD horaire_lundi VARCHAR(100) NOT NULL DEFAULT '14h00 – 18h00',
            ADD horaire_mardi VARCHAR(100) NOT NULL DEFAULT '14h00 – 18h00',
            ADD horaire_mercredi VARCHAR(100) NOT NULL DEFAULT '09h00 – 12h00',
            ADD horaire_jeudi VARCHAR(100) NOT NULL DEFAULT '14h00 – 18h00',
            ADD horaire_vendredi VARCHAR(100) NOT NULL DEFAULT '14h00 – 17h00',
            ADD horaire_samedi VARCHAR(100) NOT NULL DEFAULT 'Fermé',
            ADD horaire_dimanche VARCHAR(100) NOT NULL DEFAULT 'Fermé'");

        // Les DEFAULT ci-dessus ne servent qu'à remplir la ligne existante sans
        // erreur ; on les retire ensuite pour que la validation du formulaire
        // admin (NotBlank) reste la seule source de vérité à l'avenir.
        $this->addSql('ALTER TABLE contact_settings
            ALTER adresse DROP DEFAULT,
            ALTER telephone DROP DEFAULT,
            ALTER horaire_lundi DROP DEFAULT,
            ALTER horaire_mardi DROP DEFAULT,
            ALTER horaire_mercredi DROP DEFAULT,
            ALTER horaire_jeudi DROP DEFAULT,
            ALTER horaire_vendredi DROP DEFAULT,
            ALTER horaire_samedi DROP DEFAULT,
            ALTER horaire_dimanche DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_settings
            DROP adresse,
            DROP telephone,
            DROP horaire_lundi,
            DROP horaire_mardi,
            DROP horaire_mercredi,
            DROP horaire_jeudi,
            DROP horaire_vendredi,
            DROP horaire_samedi,
            DROP horaire_dimanche');
    }
}

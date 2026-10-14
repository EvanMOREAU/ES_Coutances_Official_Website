<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Index sur les colonnes les plus filtrées. */
final class Version20261014093054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index sur les colonnes les plus filtrées (statut, saison, échéances)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_ARTICLE_STATUT ON article (statut)');
        $this->addSql('CREATE INDEX IDX_TACHE_ECHEANCE ON contrat_partenaire_tache (fait, echeance)');
        $this->addSql('CREATE INDEX IDX_LICENCIE_STATUT ON licencie (statut)');
        $this->addSql('CREATE INDEX IDX_LICENCIE_SAISON_STATUT ON licencie (saison_id, statut)');
        $this->addSql('CREATE INDEX IDX_LICENCIE_NOM ON licencie (nom, prenom)');
        $this->addSql('CREATE INDEX IDX_PARTENAIRE_ACTIF ON partenaire (actif, ordre)');
        $this->addSql('CREATE INDEX IDX_REGLEMENT_ECHEANCE ON reglement (date_echeance, recu)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_ARTICLE_STATUT ON article');
        $this->addSql('DROP INDEX IDX_TACHE_ECHEANCE ON contrat_partenaire_tache');
        $this->addSql('DROP INDEX IDX_LICENCIE_STATUT ON licencie');
        $this->addSql('DROP INDEX IDX_LICENCIE_SAISON_STATUT ON licencie');
        $this->addSql('DROP INDEX IDX_LICENCIE_NOM ON licencie');
        $this->addSql('DROP INDEX IDX_PARTENAIRE_ACTIF ON partenaire');
        $this->addSql('DROP INDEX IDX_REGLEMENT_ECHEANCE ON reglement');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 */
final class Version20260925114946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suivi des paiements des licences : adhesion, reglement, aide_financiere';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE adhesion (id INT AUTO_INCREMENT NOT NULL, montant_base_centimes INT NOT NULL, reduction_centimes INT DEFAULT 0 NOT NULL, reduction_motif VARCHAR(150) DEFAULT NULL, observation LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, licencie_id INT NOT NULL, saison_id INT NOT NULL, INDEX IDX_C50CA65AB56DCD74 (licencie_id), INDEX IDX_C50CA65AF965414C (saison_id), UNIQUE INDEX UNIQ_ADHESION_LICENCIE_SAISON (licencie_id, saison_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE aide_financiere (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, montant_centimes INT NOT NULL, statut VARCHAR(20) DEFAULT \'attendue\' NOT NULL, date_reception DATE DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, adhesion_id INT NOT NULL, INDEX IDX_9B7685F1F68139D7 (adhesion_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reglement (id INT AUTO_INCREMENT NOT NULL, mode VARCHAR(20) NOT NULL, montant_centimes INT NOT NULL, ordre INT DEFAULT 1 NOT NULL, date_echeance DATE DEFAULT NULL, recu TINYINT DEFAULT 0 NOT NULL, date_remise DATE DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, adhesion_id INT NOT NULL, INDEX IDX_EBE4C14CF68139D7 (adhesion_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE adhesion ADD CONSTRAINT FK_C50CA65AB56DCD74 FOREIGN KEY (licencie_id) REFERENCES licencie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE adhesion ADD CONSTRAINT FK_C50CA65AF965414C FOREIGN KEY (saison_id) REFERENCES saison (id)');
        $this->addSql('ALTER TABLE aide_financiere ADD CONSTRAINT FK_9B7685F1F68139D7 FOREIGN KEY (adhesion_id) REFERENCES adhesion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reglement ADD CONSTRAINT FK_EBE4C14CF68139D7 FOREIGN KEY (adhesion_id) REFERENCES adhesion (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE adhesion DROP FOREIGN KEY FK_C50CA65AB56DCD74');
        $this->addSql('ALTER TABLE adhesion DROP FOREIGN KEY FK_C50CA65AF965414C');
        $this->addSql('ALTER TABLE aide_financiere DROP FOREIGN KEY FK_9B7685F1F68139D7');
        $this->addSql('ALTER TABLE reglement DROP FOREIGN KEY FK_EBE4C14CF68139D7');
        $this->addSql('DROP TABLE adhesion');
        $this->addSql('DROP TABLE aide_financiere');
        $this->addSql('DROP TABLE reglement');
    }
}

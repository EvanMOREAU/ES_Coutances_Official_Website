<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929023248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les codes de réduction (code_promo) et les champs de réduction / livraison sur commande.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE code_promo (
              id INT AUTO_INCREMENT NOT NULL,
              code VARCHAR(30) NOT NULL,
              description VARCHAR(255) DEFAULT NULL,
              type VARCHAR(20) NOT NULL,
              valeur INT NOT NULL,
              actif TINYINT NOT NULL,
              date_debut DATETIME DEFAULT NULL,
              date_fin DATETIME DEFAULT NULL,
              usage_max INT DEFAULT NULL,
              usage_actuel INT NOT NULL,
              autorise_livraison TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX UNIQ_5C4683B777153098 (code),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              commande
            ADD
              code_promo_code VARCHAR(30) DEFAULT NULL,
            ADD
              reduction_centimes INT NOT NULL,
            ADD
              livraison_demandee TINYINT NOT NULL,
            ADD
              livraison_adresse VARCHAR(255) DEFAULT NULL,
            ADD
              livraison_complement VARCHAR(255) DEFAULT NULL,
            ADD
              livraison_code_postal VARCHAR(10) DEFAULT NULL,
            ADD
              livraison_ville VARCHAR(100) DEFAULT NULL,
            ADD
              livraison_telephone VARCHAR(30) DEFAULT NULL,
            ADD
              livraison_instructions LONGTEXT DEFAULT NULL,
            ADD
              code_promo_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              commande
            ADD
              CONSTRAINT FK_6EEAA67D294102D4 FOREIGN KEY (code_promo_id) REFERENCES code_promo (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_6EEAA67D294102D4 ON commande (code_promo_id)');
        $this->addSql('ALTER TABLE licencie RENAME INDEX uniq_licencie_numero_personne TO UNIQ_3B755612AED90E8F');
        $this->addSql('ALTER TABLE licencie RENAME INDEX uniq_licencie_numero_licence TO UNIQ_3B755612DBFEF8E9');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE code_promo');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D294102D4');
        $this->addSql('DROP INDEX IDX_6EEAA67D294102D4 ON commande');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              commande
            DROP
              code_promo_code,
            DROP
              reduction_centimes,
            DROP
              livraison_demandee,
            DROP
              livraison_adresse,
            DROP
              livraison_complement,
            DROP
              livraison_code_postal,
            DROP
              livraison_ville,
            DROP
              livraison_telephone,
            DROP
              livraison_instructions,
            DROP
              code_promo_id
        SQL);
        $this->addSql('ALTER TABLE licencie RENAME INDEX uniq_3b755612dbfef8e9 TO UNIQ_LICENCIE_NUMERO_LICENCE');
        $this->addSql('ALTER TABLE licencie RENAME INDEX uniq_3b755612aed90e8f TO UNIQ_LICENCIE_NUMERO_PERSONNE');
    }
}

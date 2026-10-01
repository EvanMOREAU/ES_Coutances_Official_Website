<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930161719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Un bon de livraison peut être réservé à un utilisateur (utilisable dès sa création) ou ouvert à tout le monde (approuve devient requis avant utilisation).";
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE code_promo ADD approuve TINYINT NOT NULL, ADD utilisateur_id INT DEFAULT NULL');
        // Les bons déjà créés avant cette validation obligatoire restent utilisables : on les considère validés.
        $this->addSql('UPDATE code_promo SET approuve = 1');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              code_promo
            ADD
              CONSTRAINT FK_5C4683B7FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_5C4683B7FB88E14F ON code_promo (utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE code_promo DROP FOREIGN KEY FK_5C4683B7FB88E14F');
        $this->addSql('DROP INDEX IDX_5C4683B7FB88E14F ON code_promo');
        $this->addSql('ALTER TABLE code_promo DROP approuve, DROP utilisateur_id');
    }
}

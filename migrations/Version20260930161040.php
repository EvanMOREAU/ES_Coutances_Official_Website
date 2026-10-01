<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930161040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Les codes de réduction deviennent des bons de livraison : plus de réduction, plus de bascule "autorise la livraison" (tout code valide autorise la livraison).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE code_promo DROP type, DROP valeur, DROP autorise_livraison');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE
              code_promo
            ADD
              type VARCHAR(20) NOT NULL,
            ADD
              valeur INT NOT NULL,
            ADD
              autorise_livraison TINYINT NOT NULL
        SQL);
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Support client : clôture d'une discussion une fois le problème résolu (le client en ouvre alors une nouvelle). */
final class Version20261012090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie : clôture des discussions de support (date et auteur)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation ADD closed_at DATETIME DEFAULT NULL, ADD closed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9E1FA7797 FOREIGN KEY (closed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8A8E26E9E1FA7797 ON conversation (closed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9E1FA7797');
        $this->addSql('DROP INDEX IDX_8A8E26E9E1FA7797 ON conversation');
        $this->addSql('ALTER TABLE conversation DROP closed_at, DROP closed_by_id');
    }
}

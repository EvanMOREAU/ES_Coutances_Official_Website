<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Traductions automatiques (DeepL) des textes saisis en français, gardées en base. */
final class Version20261026110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table es_translation : traductions automatiques enregistrées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE es_translation (id INT AUTO_INCREMENT NOT NULL, hash VARCHAR(40) NOT NULL, locale VARCHAR(10) NOT NULL, source LONGTEXT NOT NULL, translated LONGTEXT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_translation_hash_locale (hash, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE es_translation');
    }
}

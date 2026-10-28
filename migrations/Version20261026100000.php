<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Langues du site activables par les administrateurs (le français est toujours actif). */
final class Version20261026100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Langues du site : table es_locale_setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE es_locale_setting (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(10) NOT NULL, enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_70172A8D77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO es_locale_setting (code, enabled) VALUES ('fr', 1), ('en', 1), ('de', 1), ('ar', 1), ('es', 1), ('pt', 1), ('tr', 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE es_locale_setting');
    }
}

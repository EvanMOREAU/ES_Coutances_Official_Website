<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Documents des contrats partenaires : sortis du dossier public (public/uploads) vers var/private. */
final class Version20261014090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contrats partenaires : déplace les documents hors du dossier public';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SELECT 1');
    }

    public function postUp(Schema $schema): void
    {
        $root   = dirname(__DIR__);
        $source = $root.'/public/uploads/contrats-partenaires';
        $target = $root.'/var/private/contrats-partenaires';
        if (!is_dir($source)) {
            return;
        }
        if (!is_dir($target) && !mkdir($target, 0750, true) && !is_dir($target)) {
            throw new \RuntimeException('Impossible de créer '.$target);
        }
        foreach (scandir($source) ?: [] as $file) {
            if ('.' === $file || '..' === $file || !is_file($source.'/'.$file)) {
                continue;
            }
            if (!rename($source.'/'.$file, $target.'/'.$file)) {
                throw new \RuntimeException('Impossible de déplacer '.$file);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SELECT 1');
    }
}

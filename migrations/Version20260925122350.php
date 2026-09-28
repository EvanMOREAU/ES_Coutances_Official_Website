<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 */
final class Version20260925122350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorisations : profils, autorisations par utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profil_autorisation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, permissions JSON NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7F224EBD6C6E55B5 (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("ALTER TABLE `user` ADD permissions_ajoutees JSON NOT NULL DEFAULT '[]', ADD permissions_retirees JSON NOT NULL DEFAULT '[]', ADD acces_restreint TINYINT DEFAULT 0 NOT NULL, ADD profil_id INT DEFAULT NULL");
        $this->addSql('ALTER TABLE `user` ALTER permissions_ajoutees DROP DEFAULT, ALTER permissions_retirees DROP DEFAULT');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649275ED078 FOREIGN KEY (profil_id) REFERENCES profil_autorisation (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D93D649275ED078 ON `user` (profil_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE profil_autorisation');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649275ED078');
        $this->addSql('DROP INDEX IDX_8D93D649275ED078 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP permissions_ajoutees, DROP permissions_retirees, DROP acces_restreint, DROP profil_id');
    }
}

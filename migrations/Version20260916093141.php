<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916093141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table rejoindre_card (section "Nous rejoindre" de la page d\'accueil, pilotable depuis l\'admin)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rejoindre_card (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, bouton_texte VARCHAR(100) DEFAULT NULL, bouton_url VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, actif TINYINT NOT NULL, ordre INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Reprise du contenu statique existant afin de ne rien perdre à la migration
        $this->addSql("INSERT INTO rejoindre_card (titre, description, image_name, bouton_texte, bouton_url, actif, ordre) VALUES
            ('Devenir Joueuse/Joueur', 'Rejoignez l''ES Coutances et vivez votre passion du football dans un club formateur et ambitieux.', NULL, 'En savoir plus', '/licence', 1, 0),
            ('Devenir partenaire', 'Soutenez le club et bénéficiez d''une visibilité locale et régionale auprès de plus de 500 licenciés.', NULL, 'Découvrir', '/contact', 1, 1),
            ('Devenir bénévole', 'Participez à la vie du club et contribuez à son développement au quotidien.', NULL, 'Rejoindre', '/benevoles', 1, 2),
            ('Devenir Arbitre', 'Participez à la vie du club et contribuez à son développement au quotidien.', NULL, 'Rejoindre', '/benevoles', 1, 3)
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE rejoindre_card');
    }
}

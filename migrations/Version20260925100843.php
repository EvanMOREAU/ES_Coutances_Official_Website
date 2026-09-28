<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 */
final class Version20260925100843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Planning (entrainements), boutique (articles, commandes) et etat des notifications";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, slug VARCHAR(190) NOT NULL, description LONGTEXT DEFAULT NULL, prix_centimes INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, statut VARCHAR(20) DEFAULT \'active\' NOT NULL, UNIQUE INDEX UNIQ_23A0E66989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_variante (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, stock INT DEFAULT 0 NOT NULL, ordre INT DEFAULT 0 NOT NULL, article_id INT NOT NULL, INDEX IDX_12A412DB7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(30) NOT NULL, token VARCHAR(40) NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, note LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, mode_paiement VARCHAR(20) NOT NULL, reglement VARCHAR(20) NOT NULL, total_centimes INT NOT NULL, created_at DATETIME NOT NULL, payee_le DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6EEAA67DAEA34913 (reference), UNIQUE INDEX UNIQ_6EEAA67D5F37A13B (token), INDEX IDX_6EEAA67DA76ED395 (user_id), INDEX IDX_COMMANDE_STATUT (statut), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande_ligne (id INT AUTO_INCREMENT NOT NULL, article_nom VARCHAR(150) NOT NULL, variante_libelle VARCHAR(50) NOT NULL, prix_centimes INT NOT NULL, quantite INT NOT NULL, commande_id INT NOT NULL, variante_id INT DEFAULT NULL, INDEX IDX_6E98044082EA2E54 (commande_id), INDEX IDX_6E980440D45162B6 (variante_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entrainement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, categories JSON NOT NULL, date DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME DEFAULT NULL, lieu VARCHAR(150) DEFAULT NULL, description LONGTEXT DEFAULT NULL, serie VARCHAR(36) DEFAULT NULL, created_at DATETIME DEFAULT NULL, INDEX IDX_ENTRAINEMENT_DATE (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification_state (id INT AUTO_INCREMENT NOT NULL, cle VARCHAR(190) NOT NULL, lu_le DATETIME DEFAULT NULL, masquee_le DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_65273702A76ED395 (user_id), UNIQUE INDEX UNIQ_NOTIFICATION_STATE (user_id, cle), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE article_variante ADD CONSTRAINT FK_12A412DB7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commande_ligne ADD CONSTRAINT FK_6E98044082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande_ligne ADD CONSTRAINT FK_6E980440D45162B6 FOREIGN KEY (variante_id) REFERENCES article_variante (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification_state ADD CONSTRAINT FK_65273702A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_variante DROP FOREIGN KEY FK_12A412DB7294869C');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DA76ED395');
        $this->addSql('ALTER TABLE commande_ligne DROP FOREIGN KEY FK_6E98044082EA2E54');
        $this->addSql('ALTER TABLE commande_ligne DROP FOREIGN KEY FK_6E980440D45162B6');
        $this->addSql('ALTER TABLE notification_state DROP FOREIGN KEY FK_65273702A76ED395');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_variante');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE commande_ligne');
        $this->addSql('DROP TABLE entrainement');
        $this->addSql('DROP TABLE notification_state');
    }
}

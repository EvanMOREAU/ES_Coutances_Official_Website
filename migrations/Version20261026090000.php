<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Toutes les tables de l'application portent désormais le préfixe « es_ » (voir App\Doctrine\TablePrefixListener).
 * La table de suivi des migrations (doctrine_migration_versions) garde son nom : elle est gérée par Doctrine Migrations.
 */
final class Version20261026090000 extends AbstractMigration
{
    private const TABLES = [
        'adhesion',
        'aide_financiere',
        'article',
        'article_variante',
        'audit_log',
        'boutique_settings',
        'categorie',
        'categorie_article',
        'categorie_partenaire',
        'chat_attachment',
        'code_promo',
        'commande',
        'commande_ligne',
        'contact_settings',
        'contrat_partenaire',
        'contrat_partenaire_document',
        'contrat_partenaire_reglement',
        'contrat_partenaire_tache',
        'conversation',
        'conversation_participant',
        'deployment',
        'entrainement',
        'equipe',
        'famille',
        'file_favorite',
        'helloasso_settings',
        'homepage_banner',
        'licencie',
        'licencie_equipe',
        'mail_settings',
        'match_live',
        'membre',
        'membre_categorie',
        'message',
        'messenger_messages',
        'notification_state',
        'offre_emploi',
        'page_contenu',
        'partenaire',
        'profil_autorisation',
        'reglement',
        'rejoindre_card',
        'reset_password_request',
        'saison',
        'slide_carousel',
        'user',
        'webauthn_credential',
    ];

    public function getDescription(): string
    {
        return 'Préfixe es_ sur toutes les tables';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('RENAME TABLE `%s` TO `es_%s`', $table, $table));
        }
        // Les noms d'index générés par Doctrine dépendent du nom de la table : on les aligne sur le nouveau mapping.
        $this->addSql('ALTER TABLE es_adhesion RENAME INDEX idx_c50ca65ab56dcd74 TO IDX_5ABF4299B56DCD74');
        $this->addSql('ALTER TABLE es_adhesion RENAME INDEX idx_c50ca65af965414c TO IDX_5ABF4299F965414C');
        $this->addSql('ALTER TABLE es_aide_financiere RENAME INDEX idx_9b7685f1f68139d7 TO IDX_5912DA45F68139D7');
        $this->addSql('ALTER TABLE es_article RENAME INDEX uniq_23a0e66989d9b62 TO UNIQ_E3A6820989D9B62');
        $this->addSql('ALTER TABLE es_article RENAME INDEX idx_23a0e66bcf5e72d TO IDX_E3A6820BCF5E72D');
        $this->addSql('ALTER TABLE es_article_variante RENAME INDEX idx_12a412db7294869c TO IDX_DE6A01117294869C');
        $this->addSql('ALTER TABLE es_categorie_article RENAME INDEX uniq_5db9a0c4989d9b62 TO UNIQ_26C44579989D9B62');
        $this->addSql('ALTER TABLE es_chat_attachment RENAME INDEX uniq_beae9c1d537a1329 TO UNIQ_7CCAC3A9537A1329');
        $this->addSql('ALTER TABLE es_chat_attachment RENAME INDEX idx_beae9c1d7e3c61f9 TO IDX_7CCAC3A97E3C61F9');
        $this->addSql('ALTER TABLE es_code_promo RENAME INDEX uniq_5c4683b777153098 TO UNIQ_1BF6BEE877153098');
        $this->addSql('ALTER TABLE es_code_promo RENAME INDEX idx_5c4683b7fb88e14f TO IDX_1BF6BEE8FB88E14F');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX uniq_6eeaa67daea34913 TO UNIQ_F15942BEAEA34913');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX uniq_6eeaa67d5f37a13b TO UNIQ_F15942BE5F37A13B');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX uniq_6eeaa67d38d27ab1 TO UNIQ_F15942BE38D27AB1');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX idx_6eeaa67da76ed395 TO IDX_F15942BEA76ED395');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX idx_6eeaa67d294102d4 TO IDX_F15942BE294102D4');
        $this->addSql('ALTER TABLE es_commande_ligne RENAME INDEX idx_6e98044082ea2e54 TO IDX_DD3817FC82EA2E54');
        $this->addSql('ALTER TABLE es_commande_ligne RENAME INDEX idx_6e980440d45162b6 TO IDX_DD3817FCD45162B6');
        $this->addSql('ALTER TABLE es_contrat_partenaire RENAME INDEX idx_8fc9561898de13ac TO IDX_3A62E4CC98DE13AC');
        $this->addSql('ALTER TABLE es_contrat_partenaire_document RENAME INDEX idx_c49ba96b1823061f TO IDX_16086DD61823061F');
        $this->addSql('ALTER TABLE es_contrat_partenaire_reglement RENAME INDEX idx_88fe5fb61823061f TO IDX_3DFC03431823061F');
        $this->addSql('ALTER TABLE es_contrat_partenaire_tache RENAME INDEX idx_a0458b451823061f TO IDX_1A122CF71823061F');
        $this->addSql('ALTER TABLE es_conversation RENAME INDEX idx_8a8e26e99395c3f3 TO IDX_EFC56CD39395C3F3');
        $this->addSql('ALTER TABLE es_conversation RENAME INDEX idx_8a8e26e9e1fa7797 TO IDX_EFC56CD3E1FA7797');
        $this->addSql('ALTER TABLE es_conversation_participant RENAME INDEX idx_398016619ac0396 TO IDX_83D7B1D39AC0396');
        $this->addSql('ALTER TABLE es_conversation_participant RENAME INDEX idx_39801661a76ed395 TO IDX_83D7B1D3A76ED395');
        $this->addSql('ALTER TABLE es_famille RENAME INDEX uniq_2473f213a76ed395 TO UNIQ_28739455A76ED395');
        $this->addSql('ALTER TABLE es_file_favorite RENAME INDEX idx_d9950144a76ed395 TO IDX_1FFC93BCA76ED395');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX uniq_3b755612aed90e8f TO UNIQ_A4C6B2D1AED90E8F');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX uniq_3b755612dbfef8e9 TO UNIQ_A4C6B2D1DBFEF8E9');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX uniq_3b755612a76ed395 TO UNIQ_A4C6B2D1A76ED395');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX idx_3b75561297a77b84 TO IDX_A4C6B2D197A77B84');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX idx_3b755612f965414c TO IDX_A4C6B2D1F965414C');
        $this->addSql('ALTER TABLE es_licencie_equipe RENAME INDEX idx_e1f42b84b56dcd74 TO IDX_23907430B56DCD74');
        $this->addSql('ALTER TABLE es_licencie_equipe RENAME INDEX idx_e1f42b846d861b89 TO IDX_239074306D861B89');
        $this->addSql('ALTER TABLE es_membre_categorie RENAME INDEX idx_f618d5ca6a99f74a TO IDX_3AD6C6006A99F74A');
        $this->addSql('ALTER TABLE es_membre_categorie RENAME INDEX idx_f618d5cabcf5e72d TO IDX_3AD6C600BCF5E72D');
        $this->addSql('ALTER TABLE es_message RENAME INDEX idx_b6bd307f9ac0396 TO IDX_BABD56399AC0396');
        $this->addSql('ALTER TABLE es_message RENAME INDEX idx_b6bd307ff675f31b TO IDX_BABD5639F675F31B');
        $this->addSql('ALTER TABLE es_notification_state RENAME INDEX idx_65273702a76ed395 TO IDX_D08C85D6A76ED395');
        $this->addSql('ALTER TABLE es_partenaire RENAME INDEX idx_32ffa373bcf5e72d TO IDX_754F9E2CBCF5E72D');
        $this->addSql('ALTER TABLE es_profil_autorisation RENAME INDEX uniq_7f224ebd6c6e55b5 TO UNIQ_FE29F3C26C6E55B5');
        $this->addSql('ALTER TABLE es_reglement RENAME INDEX idx_ebe4c14cf68139d7 TO IDX_E916E1A2F68139D7');
        $this->addSql('ALTER TABLE es_rejoindre_card RENAME INDEX idx_8b001a06e33ebc74 TO IDX_38A009BAE33EBC74');
        $this->addSql('ALTER TABLE es_reset_password_request RENAME INDEX idx_7ce748aa76ed395 TO IDX_9EDA521BA76ED395');
        $this->addSql('ALTER TABLE es_user RENAME INDEX uniq_8d93d649e7927c74 TO UNIQ_DFC0921CE7927C74');
        $this->addSql('ALTER TABLE es_user RENAME INDEX idx_8d93d649275ed078 TO IDX_DFC0921C275ED078');
        $this->addSql('ALTER TABLE es_saison RENAME INDEX uniq_c0d0d586a4d60759 TO UNIQ_76ADAB4DA4D60759');
        $this->addSql('ALTER TABLE es_webauthn_credential RENAME INDEX uniq_850123f92558a7a5 TO UNIQ_40A9E862558A7A5');
        $this->addSql('ALTER TABLE es_webauthn_credential RENAME INDEX idx_850123f9a76ed395 TO IDX_40A9E86A76ED395');
        $this->addSql('ALTER TABLE es_messenger_messages RENAME INDEX idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750 TO IDX_C041E434FB7336F0E3BD61CE16BA31DBBF396750');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE es_adhesion RENAME INDEX IDX_5ABF4299B56DCD74 TO idx_c50ca65ab56dcd74');
        $this->addSql('ALTER TABLE es_adhesion RENAME INDEX IDX_5ABF4299F965414C TO idx_c50ca65af965414c');
        $this->addSql('ALTER TABLE es_aide_financiere RENAME INDEX IDX_5912DA45F68139D7 TO idx_9b7685f1f68139d7');
        $this->addSql('ALTER TABLE es_article RENAME INDEX UNIQ_E3A6820989D9B62 TO uniq_23a0e66989d9b62');
        $this->addSql('ALTER TABLE es_article RENAME INDEX IDX_E3A6820BCF5E72D TO idx_23a0e66bcf5e72d');
        $this->addSql('ALTER TABLE es_article_variante RENAME INDEX IDX_DE6A01117294869C TO idx_12a412db7294869c');
        $this->addSql('ALTER TABLE es_categorie_article RENAME INDEX UNIQ_26C44579989D9B62 TO uniq_5db9a0c4989d9b62');
        $this->addSql('ALTER TABLE es_chat_attachment RENAME INDEX UNIQ_7CCAC3A9537A1329 TO uniq_beae9c1d537a1329');
        $this->addSql('ALTER TABLE es_chat_attachment RENAME INDEX IDX_7CCAC3A97E3C61F9 TO idx_beae9c1d7e3c61f9');
        $this->addSql('ALTER TABLE es_code_promo RENAME INDEX UNIQ_1BF6BEE877153098 TO uniq_5c4683b777153098');
        $this->addSql('ALTER TABLE es_code_promo RENAME INDEX IDX_1BF6BEE8FB88E14F TO idx_5c4683b7fb88e14f');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX UNIQ_F15942BEAEA34913 TO uniq_6eeaa67daea34913');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX UNIQ_F15942BE5F37A13B TO uniq_6eeaa67d5f37a13b');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX UNIQ_F15942BE38D27AB1 TO uniq_6eeaa67d38d27ab1');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX IDX_F15942BEA76ED395 TO idx_6eeaa67da76ed395');
        $this->addSql('ALTER TABLE es_commande RENAME INDEX IDX_F15942BE294102D4 TO idx_6eeaa67d294102d4');
        $this->addSql('ALTER TABLE es_commande_ligne RENAME INDEX IDX_DD3817FC82EA2E54 TO idx_6e98044082ea2e54');
        $this->addSql('ALTER TABLE es_commande_ligne RENAME INDEX IDX_DD3817FCD45162B6 TO idx_6e980440d45162b6');
        $this->addSql('ALTER TABLE es_contrat_partenaire RENAME INDEX IDX_3A62E4CC98DE13AC TO idx_8fc9561898de13ac');
        $this->addSql('ALTER TABLE es_contrat_partenaire_document RENAME INDEX IDX_16086DD61823061F TO idx_c49ba96b1823061f');
        $this->addSql('ALTER TABLE es_contrat_partenaire_reglement RENAME INDEX IDX_3DFC03431823061F TO idx_88fe5fb61823061f');
        $this->addSql('ALTER TABLE es_contrat_partenaire_tache RENAME INDEX IDX_1A122CF71823061F TO idx_a0458b451823061f');
        $this->addSql('ALTER TABLE es_conversation RENAME INDEX IDX_EFC56CD39395C3F3 TO idx_8a8e26e99395c3f3');
        $this->addSql('ALTER TABLE es_conversation RENAME INDEX IDX_EFC56CD3E1FA7797 TO idx_8a8e26e9e1fa7797');
        $this->addSql('ALTER TABLE es_conversation_participant RENAME INDEX IDX_83D7B1D39AC0396 TO idx_398016619ac0396');
        $this->addSql('ALTER TABLE es_conversation_participant RENAME INDEX IDX_83D7B1D3A76ED395 TO idx_39801661a76ed395');
        $this->addSql('ALTER TABLE es_famille RENAME INDEX UNIQ_28739455A76ED395 TO uniq_2473f213a76ed395');
        $this->addSql('ALTER TABLE es_file_favorite RENAME INDEX IDX_1FFC93BCA76ED395 TO idx_d9950144a76ed395');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX UNIQ_A4C6B2D1AED90E8F TO uniq_3b755612aed90e8f');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX UNIQ_A4C6B2D1DBFEF8E9 TO uniq_3b755612dbfef8e9');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX UNIQ_A4C6B2D1A76ED395 TO uniq_3b755612a76ed395');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX IDX_A4C6B2D197A77B84 TO idx_3b75561297a77b84');
        $this->addSql('ALTER TABLE es_licencie RENAME INDEX IDX_A4C6B2D1F965414C TO idx_3b755612f965414c');
        $this->addSql('ALTER TABLE es_licencie_equipe RENAME INDEX IDX_23907430B56DCD74 TO idx_e1f42b84b56dcd74');
        $this->addSql('ALTER TABLE es_licencie_equipe RENAME INDEX IDX_239074306D861B89 TO idx_e1f42b846d861b89');
        $this->addSql('ALTER TABLE es_membre_categorie RENAME INDEX IDX_3AD6C6006A99F74A TO idx_f618d5ca6a99f74a');
        $this->addSql('ALTER TABLE es_membre_categorie RENAME INDEX IDX_3AD6C600BCF5E72D TO idx_f618d5cabcf5e72d');
        $this->addSql('ALTER TABLE es_message RENAME INDEX IDX_BABD56399AC0396 TO idx_b6bd307f9ac0396');
        $this->addSql('ALTER TABLE es_message RENAME INDEX IDX_BABD5639F675F31B TO idx_b6bd307ff675f31b');
        $this->addSql('ALTER TABLE es_notification_state RENAME INDEX IDX_D08C85D6A76ED395 TO idx_65273702a76ed395');
        $this->addSql('ALTER TABLE es_partenaire RENAME INDEX IDX_754F9E2CBCF5E72D TO idx_32ffa373bcf5e72d');
        $this->addSql('ALTER TABLE es_profil_autorisation RENAME INDEX UNIQ_FE29F3C26C6E55B5 TO uniq_7f224ebd6c6e55b5');
        $this->addSql('ALTER TABLE es_reglement RENAME INDEX IDX_E916E1A2F68139D7 TO idx_ebe4c14cf68139d7');
        $this->addSql('ALTER TABLE es_rejoindre_card RENAME INDEX IDX_38A009BAE33EBC74 TO idx_8b001a06e33ebc74');
        $this->addSql('ALTER TABLE es_reset_password_request RENAME INDEX IDX_9EDA521BA76ED395 TO idx_7ce748aa76ed395');
        $this->addSql('ALTER TABLE es_user RENAME INDEX UNIQ_DFC0921CE7927C74 TO uniq_8d93d649e7927c74');
        $this->addSql('ALTER TABLE es_user RENAME INDEX IDX_DFC0921C275ED078 TO idx_8d93d649275ed078');
        $this->addSql('ALTER TABLE es_saison RENAME INDEX UNIQ_76ADAB4DA4D60759 TO uniq_c0d0d586a4d60759');
        $this->addSql('ALTER TABLE es_webauthn_credential RENAME INDEX UNIQ_40A9E862558A7A5 TO uniq_850123f92558a7a5');
        $this->addSql('ALTER TABLE es_webauthn_credential RENAME INDEX IDX_40A9E86A76ED395 TO idx_850123f9a76ed395');
        $this->addSql('ALTER TABLE es_messenger_messages RENAME INDEX IDX_C041E434FB7336F0E3BD61CE16BA31DBBF396750 TO idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750');
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('RENAME TABLE `es_%s` TO `%s`', $table, $table));
        }
    }
}

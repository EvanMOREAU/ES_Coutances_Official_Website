<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Associe chaque route du back-office à l'autorisation qu'elle exige.
 *
 * Les routes suivent une convention (…_index, _show, _new, _edit, _delete) qui donne
 * voir / créer / modifier / supprimer sur la ressource de leur préfixe. Les exceptions
 * sont listées explicitement. Une route inconnue est refusée par défaut : seule une
 * autorisation « oubliée » peut bloquer, jamais ouvrir un accès par mégarde.
 */
final class RoutePermissions
{
    /** Routes accessibles à tout utilisateur du back-office (profil, notifications, tableau de bord…). */
    private const FREE = [
        'admin', 'admin_notification_index', 'admin_notification_read', 'admin_notification_dismiss',
        'admin_table_preferences', 'admin_parametres_profil', 'admin_parametres_apparence', 'admin_parametres_preferences',
        'admin_parametres_securite', 'admin_parametres_securite_totp_generer', 'admin_parametres_securite_totp_confirmer',
        'admin_parametres_securite_totp_annuler', 'admin_parametres_securite_totp_desactiver',
        'admin_parametres_securite_email_activer', 'admin_parametres_securite_email_desactiver',
        'admin_parametres_securite_backup_codes_generer',
    ];

    /** Routes déjà réservées aux développeurs par leur contrôleur. */
    private const DEV_PREFIXES = [];

    /** Préfixe de route => ressource du catalogue. */
    private const RESOURCES = [
        'admin_famille'         => 'famille',
        'admin_licencie'        => 'licencie',
        'admin_equipe'          => 'equipe',
        'admin_saison'          => 'saison',
        'admin_adhesion'        => 'adhesion',
        'admin_planning'        => 'planning',
        'admin_article'         => 'article',
        'admin_boutique_categorie' => 'article',
        'admin_code_promo'      => 'code_promo',
        'admin_partenaire'      => 'partenaire',
        'admin_rejoindre_card'  => 'rejoindre_card',
        'admin_offre_emploi'    => 'offre_emploi',
        'admin_slide_carousel'  => 'slide_carousel',
        'admin_page_contenu'    => 'page_contenu',
        'admin_membre'          => 'membre',
        'admin_categorie'       => 'categorie',
        'admin_user'            => 'utilisateur',
        'admin_profil'          => 'autorisation',
    ];

    /** Routes « hub » : visibles dès que l'on a accès à l'une de leurs rubriques. */
    private const HUBS = [
        'admin_licencies_hub' => 'famille.voir,licencie.voir,equipe.voir,saison.voir,adhesion.voir,import.voir',
        'admin_site_vitrine'  => 'partenaire.voir,rejoindre_card.voir,offre_emploi.voir,slide_carousel.voir,page_contenu.voir,membre.voir,reglages.voir',
        'admin_import_hub'    => 'import.voir',
        'admin_acces_hub'     => 'utilisateur.voir,autorisation.voir',
        'admin_configuration_hub' => 'mail.voir,helloasso.voir,categorie.voir',
        'admin_content_image_upload' => 'page_contenu.modifier,page_contenu.creer,article.modifier,article.creer,offre_emploi.modifier,offre_emploi.creer,membre.modifier,membre.creer,rejoindre_card.modifier,rejoindre_card.creer',
    ];

    /** Exceptions à la convention. */
    private const EXPLICIT = [
        'admin_licencie_resend'        => 'licencie.renvoyer_acces',
        'admin_licencie_correction_import' => 'licencie.corriger_import',
        'admin_mail_test'              => 'mail.tester',
        'admin_user_resend_activation' => 'utilisateur.modifier',
        'admin_adhesion_suivi'         => 'adhesion.suivi',
        'admin_adhesion_edit'          => 'adhesion.modifier',
        'admin_rejoindre_card_toggle'  => 'rejoindre_card.modifier',
        'admin_article_stock'          => 'article.modifier',
        'admin_planning_events'        => 'planning.voir',
        'admin_planning_create'        => 'planning.creer',
        'admin_planning_update'        => 'planning.modifier',
        'admin_import_index'           => 'import.voir',
        'admin_import_preview'         => 'import.executer',
        'admin_import_confirm'         => 'import.executer',
        'admin_commande_index'         => 'commande.voir',
        'admin_commande_show'          => 'commande.voir',
        'admin_files_index'            => 'fichiers.voir',
        'admin_files_list'             => 'fichiers.voir',
        'admin_files_usage'            => 'fichiers.voir',
        'admin_files_view'             => 'fichiers.voir',
        'admin_files_download'         => 'fichiers.voir',
        'admin_files_star'             => 'fichiers.voir',
        'admin_files_mkdir'            => 'fichiers.televerser',
        'admin_files_upload'           => 'fichiers.televerser',
        'admin_files_rename'           => 'fichiers.modifier',
        'admin_files_delete'           => 'fichiers.supprimer',
        'admin_categorie_reorder'      => 'categorie.modifier',
        'admin_deploy_index'           => 'deploiement.voir',
        'admin_deploy_check'           => 'deploiement.voir',
        'admin_deploy_status'          => 'deploiement.voir',
        'admin_deploy_start'           => 'deploiement.lancer',
        'admin_journal_index'          => 'journal.voir',
        'admin_journal_show'           => 'journal.voir',
        'admin_journal_export'         => 'journal.exporter',
        'admin_changelog_index'        => 'changelog.voir',
    ];

    /** Ressource d'une action groupée / d'une bascule d'activation, d'après son type. */
    private const TYPES = [
        'famille' => 'famille', 'licencie' => 'licencie', 'equipe' => 'equipe', 'saison' => 'saison',
        'partenaire' => 'partenaire', 'membre' => 'membre', 'offre_emploi' => 'offre_emploi',
        'slide_carousel' => 'slide_carousel', 'page_contenu' => 'page_contenu', 'rejoindre_card' => 'rejoindre_card',
        'article' => 'article',
    ];

    /** La route est-elle réservée aux développeurs (déjà protégée par son contrôleur) ? */
    public static function isDev(string $route): bool
    {
        foreach (self::DEV_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Autorisation exigée par une route : un code, une liste « a,b,c » (l'une suffit),
     * null (route libre) ou false (route inconnue, refusée par défaut).
     *
     * @param array<string, mixed> $params paramètres de la route (type, action…)
     */
    public static function required(string $route, string $method, array $params = [], ?Request $request = null): string|false|null
    {
        if (in_array($route, self::FREE, true) || self::isDev($route)) {
            return null;
        }
        if (isset(self::HUBS[$route])) {
            return self::HUBS[$route];
        }
        if (str_starts_with($route, 'admin_chat_')) {
            return 'messagerie.utiliser';
        }
        if ('admin_mail_settings' === $route) {
            return 'GET' === $method || 'HEAD' === $method ? 'mail.voir' : 'mail.modifier';
        }
        if ('admin_helloasso_settings' === $route) {
            return 'GET' === $method || 'HEAD' === $method ? 'helloasso.voir' : 'helloasso.modifier';
        }
        if (str_starts_with($route, 'admin_reglages_')) {
            return 'GET' === $method || 'HEAD' === $method ? 'reglages.voir' : 'reglages.modifier';
        }
        if ('admin_boutique_maintenance' === $route) {
            return 'GET' === $method || 'HEAD' === $method ? 'boutique_maintenance.voir' : 'boutique_maintenance.modifier';
        }
        if ('admin_bulk_action' === $route || 'admin_toggle_actif' === $route) {
            $resource = self::TYPES[$params['type'] ?? ''] ?? null;
            if (!$resource) {
                return false;
            }
            $delete = 'admin_bulk_action' === $route && 'delete' === (string) $request?->request->get('action');

            return $resource.'.'.($delete ? 'supprimer' : 'modifier');
        }
        if ('admin_commande_action' === $route) {
            return 'annuler' === ($params['action'] ?? '') ? 'commande.annuler' : 'commande.traiter';
        }
        if (isset(self::EXPLICIT[$route])) {
            return self::EXPLICIT[$route];
        }

        foreach (self::RESOURCES as $prefix => $resource) {
            if (!str_starts_with($route, $prefix.'_')) {
                continue;
            }
            $suffix = substr($route, strlen($prefix) + 1);

            return $resource.'.'.match (true) {
                'index' === $suffix, 'show' === $suffix => PermissionCatalog::VOIR,
                'new' === $suffix                       => PermissionCatalog::CREER,
                'edit' === $suffix                      => PermissionCatalog::MODIFIER,
                'delete' === $suffix                    => PermissionCatalog::SUPPRIMER,
                default                                 => '?',
            };
        }

        return false;
    }
}

<?php

namespace App\Audit;

/**
 * Libellés lisibles des éléments du site dans le journal : nom courant (« Famille », « Article »…)
 * et rubrique (Licenciés, Boutique…). Un élément absent d'ici est tout de même journalisé,
 * sous le nom de sa classe et la rubrique « Autre ».
 */
final class AuditCatalog
{
    /** Classes (nom court) dont les modifications ne sont pas journalisées (bruit ou contenu privé). */
    public const IGNORED_CLASSES = [
        'AuditLog', 'NotificationState', 'ResetPasswordRequest', 'FileFavorite',
        // Messagerie : le contenu des échanges est privé ; l'usage de la messagerie est tracé au niveau des requêtes.
        'Message', 'Conversation', 'ConversationParticipant',
    ];

    /** Champs dont le changement seul ne mérite pas une ligne. */
    public const IGNORED_FIELDS = ['lastSeenAt', 'updatedAt', 'tablePreferences', 'log', 'step'];

    /** @var array<string, array{0: string, 1: string}> nom court de classe => [libellé, rubrique] */
    private const ENTITIES = [
        'Famille'           => ['Famille', 'Licenciés'],
        'Licencie'          => ['Licencié', 'Licenciés'],
        'Equipe'            => ['Équipe', 'Licenciés'],
        'Saison'            => ['Saison', 'Licenciés'],
        'Adhesion'          => ['Licence (paiement)', 'Paiements des licences'],
        'Reglement'         => ['Règlement', 'Paiements des licences'],
        'AideFinanciere'    => ['Aide financière', 'Paiements des licences'],
        'Entrainement'      => ['Séance du planning', 'Planning'],
        'Article'           => ['Article', 'Boutique — articles'],
        'ArticleVariante'   => ['Taille d\'article', 'Boutique — articles'],
        'Commande'          => ['Commande', 'Boutique — commandes'],
        'CommandeLigne'     => ['Ligne de commande', 'Boutique — commandes'],
        'Partenaire'        => ['Partenaire', 'Site vitrine'],
        'RejoindreCard'     => ['Carte « Nous rejoindre »', 'Site vitrine'],
        'OffreEmploi'       => ['Offre d\'emploi', 'Site vitrine'],
        'SlideCarousel'     => ['Slide du carousel', 'Site vitrine'],
        'PageContenu'       => ['Page de contenu', 'Site vitrine'],
        'Membre'            => ['Membre de l\'encadrement', 'Site vitrine'],
        'Categorie'         => ['Catégorie d\'encadrement', 'Site vitrine'],
        'ContactSettings'   => ['Réglages de contact', 'Site vitrine'],
        'HomepageBanner'    => ['Bannière d\'accueil', 'Site vitrine'],
        'MatchLive'         => ['Match en live', 'Site vitrine'],
        'User'              => ['Utilisateur', 'Utilisateurs et autorisations'],
        'ProfilAutorisation' => ['Profil d\'autorisation', 'Utilisateurs et autorisations'],
        'MailSettings'      => ['Paramètres e-mail', 'Paramètres'],
        'Deployment'        => ['Mise à jour du site', 'Système'],
    ];

    /** @return array{0: string, 1: string} [libellé, rubrique] */
    public static function entity(string $class): array
    {
        $short = substr(strrchr('\\'.$class, '\\'), 1);

        return self::ENTITIES[$short] ?? [$short, 'Autre'];
    }

    /** @return list<string> toutes les rubriques connues (filtre du journal) */
    public static function categories(): array
    {
        $categories = array_unique(array_column(self::ENTITIES, 1));
        $categories = array_merge($categories, ['Sécurité', 'E-mails', 'Boutique (site public)', 'Espace licenciés', 'Fichiers', 'Messagerie', 'Import', 'Système', 'Autre']);

        return array_values(array_unique($categories));
    }
}

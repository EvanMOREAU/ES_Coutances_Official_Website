<?php

namespace App\Security;

/**
 * Catalogue de toutes les autorisations du back-office, rangées par rubrique.
 *
 * Chaque autorisation est un code « ressource.action » (ex. famille.modifier). Ce fichier est
 * la source unique : la matrice des profils, la vérification (PermissionChecker) et les
 * gabarits (fonction Twig can) en dépendent. Pour protéger une nouvelle page, ajoutez ici son
 * autorisation puis associez sa route dans RoutePermissions.
 */
final class PermissionCatalog
{
    public const VOIR      = 'voir';
    public const CREER     = 'creer';
    public const MODIFIER  = 'modifier';
    public const SUPPRIMER = 'supprimer';

    /** Colonnes « standard » de la matrice, dans l'ordre. */
    public const STANDARD = [
        self::VOIR      => 'Voir',
        self::CREER     => 'Créer',
        self::MODIFIER  => 'Modifier',
        self::SUPPRIMER => 'Supprimer',
    ];

    /**
     * rubrique => ressource => [libellé, actions]. Les actions sont soit les quatre standard,
     * soit des actions particulières (code => libellé).
     *
     * @return array<string, array<string, array{label: string, actions: array<string, string>}>>
     */
    public static function groups(): array
    {
        $crud = self::STANDARD;

        return [
            'Licenciés' => [
                'famille'  => ['label' => 'Familles', 'actions' => $crud],
                'licencie' => ['label' => 'Licenciés', 'actions' => $crud + ['renvoyer_acces' => 'Renvoyer l\'accès (mot de passe)']],
                'equipe'   => ['label' => 'Équipes', 'actions' => $crud],
                'saison'   => ['label' => 'Saisons', 'actions' => $crud],
                'adhesion' => ['label' => 'Paiements des licences', 'actions' => [
                    self::VOIR => 'Voir', self::CREER => 'Créer', self::MODIFIER => 'Modifier', self::SUPPRIMER => 'Supprimer',
                    'suivi' => 'Suivi des règlements et des aides',
                ]],
                'import'   => ['label' => 'Import Foot Club', 'actions' => [self::VOIR => 'Voir', 'executer' => 'Lancer un import']],
            ],
            'Planning' => [
                'planning' => ['label' => 'Entraînements et rencontres', 'actions' => $crud],
            ],
            'Boutique' => [
                'article'  => ['label' => 'Articles', 'actions' => $crud],
                'commande' => ['label' => 'Commandes', 'actions' => [
                    self::VOIR => 'Voir', 'traiter' => 'Préparer, encaisser, remettre', 'annuler' => 'Annuler une commande',
                ]],
            ],
            'Site vitrine' => [
                'partenaire'     => ['label' => 'Partenaires et sponsors', 'actions' => $crud],
                'rejoindre_card' => ['label' => 'Cartes « Nous rejoindre »', 'actions' => $crud],
                'offre_emploi'   => ['label' => 'Offres d\'emploi', 'actions' => $crud],
                'slide_carousel' => ['label' => 'Carousel de l\'accueil', 'actions' => $crud],
                'page_contenu'   => ['label' => 'Pages de contenu', 'actions' => $crud],
                'membre'         => ['label' => 'Encadrement', 'actions' => $crud],
                'reglages'       => ['label' => 'Réglages (accueil, contact, match en live)', 'actions' => [self::VOIR => 'Voir', self::MODIFIER => 'Modifier']],
            ],
            'Administration' => [
                'utilisateur'  => ['label' => 'Utilisateurs', 'actions' => $crud],
                'autorisation' => ['label' => 'Profils d\'autorisation', 'actions' => $crud],
                'categorie'    => ['label' => 'Catégories d\'encadrement', 'actions' => $crud],
                'fichiers'     => ['label' => 'Fichiers', 'actions' => [
                    self::VOIR => 'Voir et télécharger', 'televerser' => 'Envoyer, créer un dossier', self::MODIFIER => 'Renommer', self::SUPPRIMER => 'Supprimer',
                ]],
                'mail'         => ['label' => 'Paramètres e-mail (serveur SMTP)', 'actions' => [self::VOIR => 'Voir', self::MODIFIER => 'Modifier', 'tester' => 'Envoyer un e-mail de test']],
                'messagerie'   => ['label' => 'Messagerie', 'actions' => ['utiliser' => 'Utiliser la messagerie']],
                'boutique_maintenance' => ['label' => 'Maintenance de la boutique', 'actions' => [self::VOIR => 'Voir', self::MODIFIER => 'Modifier']],
                'deploiement'  => ['label' => 'Déploiement', 'actions' => [self::VOIR => 'Voir', 'lancer' => 'Lancer un déploiement']],
                'journal'      => ['label' => 'Journal d\'activité', 'actions' => [self::VOIR => 'Voir', 'exporter' => 'Exporter en CSV']],
                'changelog'    => ['label' => 'Changelog', 'actions' => [self::VOIR => 'Voir']],
            ],
        ];
    }

    /** @return list<string> tous les codes d'autorisation */
    public static function all(): array
    {
        $codes = [];
        foreach (self::groups() as $resources) {
            foreach ($resources as $resource => $definition) {
                foreach (array_keys($definition['actions']) as $action) {
                    $codes[] = $resource.'.'.$action;
                }
            }
        }

        return $codes;
    }

    public static function exists(string $code): bool
    {
        return in_array($code, self::all(), true);
    }

    /** Ne garde que les codes connus (les données stockées peuvent contenir d'anciens codes). @param iterable<mixed> $codes @return list<string> */
    public static function sanitize(iterable $codes): array
    {
        $known = array_flip(self::all());

        return array_values(array_unique(array_filter(
            array_map('strval', is_array($codes) ? $codes : iterator_to_array($codes)),
            static fn (string $c) => isset($known[$c]),
        )));
    }

    public static function label(string $code): string
    {
        [$resource, $action] = explode('.', $code, 2) + [1 => ''];
        foreach (self::groups() as $resources) {
            if (isset($resources[$resource])) {
                return sprintf('%s — %s', $resources[$resource]['label'], $resources[$resource]['actions'][$action] ?? $action);
            }
        }

        return $code;
    }
}

<?php

namespace App\Service\Notification;

use App\Entity\User;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Security\PermissionChecker;

/**
 * Catalogue des notifications que chaque compte peut choisir de recevoir, dans la clochette de
 * l'administration (« bell ») et/ou par e-mail (« email »). Tout est activé par défaut.
 *
 * Ne figurent PAS ici les e-mails indispensables de la boutique (confirmation de commande,
 * paiement validé, commande prête…) ni ceux de sécurité (code de connexion, mot de passe) :
 * ils sont toujours envoyés.
 *
 * Seuls les choix explicites sont mémorisés ; l'ancien format (simple liste de clés) est ignoré.
 */
class NotificationPreferences
{
    public const BELL  = 'bell';
    public const EMAIL = 'email';

    /**
     * audience : « all » (tout compte), « staff » (équipe du club uniquement).
     * permission : exigée d'un membre de l'équipe pour que la rubrique lui soit proposée.
     * channels : canaux disponibles.
     */
    public const CATALOG = [
        'messages' => [
            'label' => 'Nouveaux messages', 'help' => "Quelqu'un vous écrit dans la messagerie.",
            'audience' => 'all', 'permission' => 'messagerie.utiliser,messagerie.support', 'channels' => [self::BELL, self::EMAIL],
        ],
        'planning' => [
            'label' => 'Entraînements, rencontres et événements', 'help' => 'Un événement du planning qui vous concerne est créé ou approche.',
            'audience' => 'all', 'permission' => 'planning.voir', 'channels' => [self::BELL, self::EMAIL], 'members_only' => true,
        ],
        'commandes' => [
            'label' => 'Nouvelles commandes de la boutique', 'help' => 'Un client passe une commande.',
            'audience' => 'staff', 'permission' => 'commande.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'familles' => [
            'label' => 'Nouvelles familles', 'help' => "Une famille est ajoutée ou s'inscrit.",
            'audience' => 'staff', 'permission' => 'famille.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'licencies' => [
            'label' => 'Nouveaux licenciés', 'help' => 'Un licencié est ajouté.',
            'audience' => 'staff', 'permission' => 'licencie.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'utilisateurs' => [
            'label' => 'Nouveaux comptes utilisateurs', 'help' => 'Un compte (client, famille, licencié) est créé.',
            'audience' => 'staff', 'permission' => 'utilisateur.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'conseils' => [
            'label' => 'Conseils et points à corriger', 'help' => 'Éléments du site à compléter, e-mails de familles invalides…',
            'audience' => 'staff', 'permission' => null, 'channels' => [self::BELL],
        ],
    ];

    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly FamilleRepository $familles,
        private readonly LicencieRepository $licencies,
    ) {
    }

    /** Famille ou licencié du club (par opposition à un simple client de la boutique). */
    private function isMember(User $user): bool
    {
        return null !== $this->familles->findOneBy(['user' => $user]) || null !== $this->licencies->findOneBy(['user' => $user]);
    }

    /**
     * Rubriques proposées à ce compte, avec les canaux qui s'appliquent à lui (pas de clochette hors équipe).
     *
     * @return array<string, array{label: string, help: string, channels: list<string>}>
     */
    public function availableFor(User $user): array
    {
        $available = [];
        foreach (self::CATALOG as $key => $entry) {
            if ($entry['audience'] === 'staff' && !$user->isStaff()) {
                continue;
            }
            if (!$user->isStaff() && ($entry['members_only'] ?? false) && !$this->isMember($user)) {
                continue;
            }
            if ($user->isStaff() && $entry['permission'] !== null && !$this->permissions->can($entry['permission'], $user)) {
                continue;
            }
            $channels = array_values(array_filter($entry['channels'], static fn (string $c) => $c !== self::BELL || $user->isStaff()));
            if ($channels === []) {
                continue;
            }
            $available[$key] = ['label' => $entry['label'], 'help' => $entry['help'], 'channels' => $channels];
        }

        return $available;
    }

    /** Ce compte souhaite-t-il cette notification sur ce canal ? (oui tant qu'il ne l'a pas désactivée) */
    public function wants(User $user, string $key, string $channel): bool
    {
        $stored = $user->getNotificationPreferences();
        $value  = $stored[$channel][$key] ?? null;

        return $value === null ? true : (bool) $value;
    }

    /**
     * Enregistre les choix du formulaire : chaque case proposée à l'utilisateur est mémorisée (cochée ou non).
     *
     * @param array<string, array<string, mixed>> $submitted [canal => [clé => valeur]] (une case décochée est absente)
     */
    public function save(User $user, array $submitted): void
    {
        $prefs = [self::BELL => [], self::EMAIL => []];
        foreach ($this->availableFor($user) as $key => $entry) {
            foreach ($entry['channels'] as $channel) {
                $prefs[$channel][$key] = isset($submitted[$channel][$key]);
            }
        }
        $user->setNotificationPreferences($prefs);
    }
}

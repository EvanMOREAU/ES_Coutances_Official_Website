<?php

namespace App\Service\Notification;

use App\Entity\User;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Security\PermissionChecker;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Catalogue des notifications que chaque compte peut choisir de recevoir, dans la clochette de
 * l'administration (« bell ») et/ou par e-mail (« email »). Tout est activé par défaut.
 *
 * Ne figurent PAS ici les e-mails indispensables de la boutique (confirmation de commande,
 * paiement validé, commande prête…) ni ceux de sécurité (code de connexion, mot de passe) :
 * ils sont toujours envoyés.
 *
 * Seuls les choix explicites sont mémorisés ; l'ancien format (simple liste de clés) est ignoré.
 * Les libellés vivent dans translations/messages.*.yaml sous « notif.catalog.<clé>.label|help ».
 */
class NotificationPreferences
{
    public const BELL  = 'bell';
    public const EMAIL = 'email';

    /**
     * audience : « all » (tout compte), « staff » (équipe du club), « dev » (développeurs uniquement).
     * permission : exigée d'un membre de l'équipe pour que la rubrique lui soit proposée.
     * channels : canaux disponibles.
     * members_only : pour un compte hors équipe, proposée seulement aux familles et licenciés.
     */
    public const CATALOG = [
        'messages' => [
            'audience' => 'all', 'permission' => 'messagerie.utiliser,messagerie.support', 'channels' => [self::BELL, self::EMAIL],
        ],
        'planning' => [
            'audience' => 'all', 'permission' => 'planning.voir', 'channels' => [self::BELL, self::EMAIL], 'members_only' => true,
        ],
        'rappels' => [
            'audience' => 'all', 'permission' => 'planning.voir', 'channels' => [self::EMAIL], 'members_only' => true,
        ],
        'mes_licencies' => [
            'audience' => 'members', 'permission' => null, 'channels' => [self::EMAIL], 'members_only' => true,
        ],
        'paiements' => [
            'audience' => 'members', 'permission' => null, 'channels' => [self::EMAIL], 'members_only' => true,
        ],
        'commandes' => [
            'audience' => 'staff', 'permission' => 'commande.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'stock' => [
            'audience' => 'staff', 'permission' => 'article.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'familles' => [
            'audience' => 'staff', 'permission' => 'famille.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'licencies' => [
            'audience' => 'staff', 'permission' => 'licencie.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'adhesions' => [
            'audience' => 'staff', 'permission' => 'adhesion.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'utilisateurs' => [
            'audience' => 'staff', 'permission' => 'utilisateur.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'partenaires' => [
            'audience' => 'staff', 'permission' => 'partenaire.voir', 'channels' => [self::BELL, self::EMAIL],
        ],
        'contact' => [
            'audience' => 'staff', 'permission' => 'reglages.voir', 'channels' => [self::EMAIL],
        ],
        'conseils' => [
            'audience' => 'staff', 'permission' => null, 'channels' => [self::BELL],
        ],
        'systeme' => [
            'audience' => 'dev', 'permission' => null, 'channels' => [self::BELL],
        ],
    ];

    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly FamilleRepository $familles,
        private readonly LicencieRepository $licencies,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Famille ou licencié du club (par opposition à un simple client de la boutique). */
    public function isMember(User $user): bool
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
        $isStaff = $user->isStaff();
        $isDev   = \in_array('ROLE_DEV', $user->getRoles(), true);

        $available = [];
        foreach (self::CATALOG as $key => $entry) {
            if ('staff' === $entry['audience'] && !$isStaff) {
                continue;
            }
            if ('dev' === $entry['audience'] && !$isDev) {
                continue;
            }
            if ('members' === $entry['audience'] && !$this->isMember($user)) {
                continue;
            }
            if (!$isStaff && ($entry['members_only'] ?? false) && !$this->isMember($user)) {
                continue;
            }
            if ($isStaff && null !== $entry['permission'] && !$this->permissions->can($entry['permission'], $user)) {
                continue;
            }
            $channels = array_values(array_filter($entry['channels'], static fn (string $c) => self::BELL !== $c || $isStaff));
            if ([] === $channels) {
                continue;
            }
            $available[$key] = [
                'label'    => $this->translator->trans('notif.catalog.'.$key.'.label'),
                'help'     => $this->translator->trans('notif.catalog.'.$key.'.help'),
                'channels' => $channels,
            ];
        }

        return $available;
    }

    /** Ce compte souhaite-t-il cette notification sur ce canal ? (oui tant qu'il ne l'a pas désactivée) */
    public function wants(User $user, string $key, string $channel): bool
    {
        $stored = $user->getNotificationPreferences();
        $value  = $stored[$channel][$key] ?? null;

        return null === $value ? true : (bool) $value;
    }

    /**
     * Enregistre les choix du formulaire : chaque case proposée à l'utilisateur est mémorisée (cochée ou non).
     *
     * @param array<string, array<string, mixed>> $submitted [canal => [clé => valeur]] (une case décochée est absente)
     */
    public function save(User $user, array $submitted): void
    {
        // Les choix déjà mémorisés pour des rubriques qui ne sont (plus) pas proposées sont conservés tels quels.
        $prefs = $user->getNotificationPreferences() + [self::BELL => [], self::EMAIL => []];
        foreach ($this->availableFor($user) as $key => $entry) {
            foreach ($entry['channels'] as $channel) {
                $prefs[$channel][$key] = isset($submitted[$channel][$key]);
            }
        }
        $user->setNotificationPreferences($prefs);
    }
}

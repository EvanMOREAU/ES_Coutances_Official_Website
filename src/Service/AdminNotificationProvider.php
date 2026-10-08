<?php

namespace App\Service;

use App\Entity\Famille;
use App\Entity\NotificationState;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\EntrainementRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Repository\NotificationStateRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Service\Chat\ChatService;
use App\Service\Notification\NotificationPreferences;
use Doctrine\ORM\EntityManagerInterface;
use SortDirection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notifications de la clochette du back-office. Elles sont calculées à la
 * volée à partir des données existantes (activité récente, points d'attention,
 * commandes à préparer) et chacune porte une clé stable. Ce que l'utilisateur
 * en fait — lue, masquée — est mémorisé par clé dans NotificationState.
 *
 * Une notification masquée ne réapparaît pas ; une notification dont le
 * contenu change (ex. le nombre de conseils à compléter) reçoit une nouvelle
 * clé et redevient donc non lue.
 */
class AdminNotificationProvider
{
    /** @var list<array{key: string, icon: string, text: string, meta: string, url: string, read: bool}>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly Security $security,
        private readonly FamilleRepository $familleRepository,
        private readonly CommandeRepository $commandeRepository,
        private readonly NotificationStateRepository $states,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
        private readonly SiteAdvisor $advisor,
        private readonly PermissionChecker $permissions,
        private readonly NotificationPreferences $prefs,
        private readonly LicencieRepository $licencieRepository,
        private readonly UserRepository $userRepository,
        private readonly EntrainementRepository $entrainementRepository,
        private readonly PlanningService $planning,
        private readonly ChatService $chat,
    ) {
    }

    /**
     * Notifications visibles (non masquées) de l'utilisateur, non lues d'abord.
     *
     * @return list<array{key: string, icon: string, text: string, meta: string, url: string, read: bool}>
     */
    public function getNotifications(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('ROLE_EDITOR')) {
            return $this->cache = [];
        }

        $states = $this->states->indexedFor($user);
        $items  = [];
        foreach ($this->compute() as $notification) {
            $state = $states[$notification['key']] ?? null;
            if ($state?->isMasquee()) {
                continue;
            }
            $items[] = $notification + ['read' => (bool) $state?->isLue()];
        }

        // Non lues d'abord ; l'ordre d'origine est conservé à l'intérieur de chaque groupe.
        $unread = array_values(array_filter($items, static fn (array $n) => !$n['read']));
        $read   = array_values(array_filter($items, static fn (array $n) => $n['read']));

        return $this->cache = [...$unread, ...$read];
    }

    public function unreadCount(): int
    {
        return count(array_filter($this->getNotifications(), static fn (array $n) => !$n['read']));
    }

    /**
     * Marque comme lues les notifications données (toutes si $keys est null).
     *
     * @param list<string>|null $keys
     */
    public function markRead(?array $keys = null): void
    {
        $this->apply($keys, static fn (NotificationState $s) => $s->marquerLue());
    }

    /**
     * Masque les notifications données (toutes si $keys est null).
     *
     * @param list<string>|null $keys
     */
    public function dismiss(?array $keys = null): void
    {
        $this->apply($keys, static fn (NotificationState $s) => $s->masquer());
    }

    /** @param list<string>|null $keys */
    private function apply(?array $keys, callable $change): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Seules les clés de notifications réellement affichées sont acceptées.
        $known = array_column($this->getNotifications(), 'key');
        $keys  = null === $keys ? $known : array_values(array_intersect($keys, $known));
        if ([] === $keys) {
            return;
        }

        $states = $this->states->indexedFor($user);
        foreach ($keys as $key) {
            $state = $states[$key] ?? new NotificationState($user, $key);
            $change($state);
            $this->em->persist($state);
        }
        $this->em->flush();
        $this->cache = null;
    }

    /** @return list<array{key: string, icon: string, text: string, meta: string, url: string}> */
    private function compute(): array
    {
        $notifications = [];
        $me    = $this->security->getUser();
        $wants = fn (string $key): bool => $me instanceof User && $this->prefs->wants($me, $key, NotificationPreferences::BELL);

        // Messages non lus (messagerie).
        if ($me instanceof User && $wants('messages') && $this->permissions->can('messagerie.utiliser,messagerie.support')) {
            $unread = $this->chat->unreadTotal($me);
            if ($unread > 0) {
                $notifications[] = [
                    'key'  => 'messages:'.$unread,
                    'icon' => 'fa-comments',
                    'text' => sprintf('%d message%s non lu%s', $unread, $unread > 1 ? 's' : '', $unread > 1 ? 's' : ''),
                    'meta' => 'Messagerie',
                    'url'  => $this->urls->generate('admin_chat_index'),
                ];
            }
        }

        // Prochains événements du planning qui concernent ce compte (3 jours).
        if ($me instanceof User && $wants('planning') && $this->permissions->can('planning.voir')) {
            $shown = 0;
            foreach ($this->entrainementRepository->between(new \DateTimeImmutable('today'), new \DateTimeImmutable('+3 days')) as $event) {
                if (!$this->planning->visibleEvenement($event, $me) || $shown >= 3) {
                    continue;
                }
                ++$shown;
                $notifications[] = [
                    'key'  => 'planning:'.$event->getId(),
                    'icon' => 'fa-calendar-day',
                    'text' => sprintf('%s — %s', $event->getTitre(), $event->getDate()?->format('d/m').' à '.$event->getHeureDebut()?->format('H:i')),
                    'meta' => 'Planning',
                    'url'  => $this->urls->generate('admin_planning_index'),
                ];
            }
        }

        // Commandes de la boutique à préparer.
        foreach ($wants('commandes') && $this->permissions->can('commande.voir') ? $this->commandeRepository->findAPreparer(5) : [] as $commande) {
            $notifications[] = [
                'key'  => 'commande:' . $commande->getId(),
                'icon' => 'fa-bag-shopping',
                'text' => sprintf('Nouvelle commande %s de %s', $commande->getReference(), $commande->getNomComplet()),
                'meta' => sprintf('Boutique · %s · %s', Money::format($commande->getTotalCentimes()), $commande->isPayee() ? 'payée' : 'règlement en attente'),
                'url'  => $this->urls->generate('admin_commande_show', ['id' => $commande->getId()]),
            ];
        }

        // Conseils de configuration : simples invitations à compléter le site, jamais bloquantes.
        $hubs = [
            SiteAdvisor::HUB_VITRINE    => ['vitrine', 'Site vitrine', '/admin/site-vitrine'],
            SiteAdvisor::HUB_LICENCIES  => ['licencies', 'Gestion des licenciés', '/admin/gestion-licencies'],
            SiteAdvisor::HUB_PARTENAIRE => ['partenaire', 'Partenaires', '/admin/partenaires/hub'],
        ];
        $hubPermission = [
            SiteAdvisor::HUB_VITRINE    => 'rejoindre_card.voir,offre_emploi.voir,slide_carousel.voir,page_contenu.voir,membre.voir,reglages.voir',
            SiteAdvisor::HUB_LICENCIES  => 'famille.voir,licencie.voir,equipe.voir,saison.voir',
            SiteAdvisor::HUB_PARTENAIRE => 'partenaire.voir',
        ];
        foreach ($hubs as $hub => [$slug, $label, $url]) {
            if (!$wants('conseils') || !$this->permissions->can($hubPermission[$hub])) {
                continue;
            }
            $count = $this->advisor->count($hub);
            if ($count > 0) {
                $notifications[] = [
                    'key'  => sprintf('conseil:%s:%d', $slug, $count),
                    'icon' => 'fa-lightbulb',
                    'text' => sprintf('%s : %d élément%s à compléter', $label, $count, $count > 1 ? 's' : ''),
                    'meta' => 'Conseil de configuration',
                    'url'  => $url,
                ];
            }
        }

        // Familles sans email exploitable (adresse invalide, ou provisoire générée par un import) :
        // la clé inclut le compte pour que la notification réapparaisse si elle a été masquée puis
        // que le nombre de familles concernées change à nouveau.
        if ($wants('conseils') && $this->permissions->can('famille.voir')) {
            $sansEmailValide = array_filter($this->familleRepository->findAll(), static fn (Famille $f) => !$f->hasEmailValide());
            $count = count($sansEmailValide);
            if ($count > 0) {
                $notifications[] = [
                    'key'  => sprintf('email-invalide:%d', $count),
                    'icon' => 'fa-envelope-circle-exclamation',
                    'text' => sprintf('%d famille%s sans email valide', $count, $count > 1 ? 's' : ''),
                    'meta' => 'À corriger',
                    'url'  => $this->urls->generate('admin_famille_index'),
                ];
            }
        }

        foreach ($wants('familles') && $this->permissions->can('famille.voir') ? $this->familleRepository->findBy([], ['id' => 'DESC'], 3) : [] as $famille) {
            $notifications[] = [
                'key'  => 'famille:' . $famille->getId(),
                'icon' => 'fa-house-user',
                'text' => sprintf('Nouvelle famille : %s', $famille->getNom()),
                'meta' => 'Famille',
                'url'  => '/admin/familles/' . $famille->getId(),
            ];
        }

        foreach ($wants('licencies') && $this->permissions->can('licencie.voir') ? $this->licencieRepository->findBy([], ['id' => 'DESC'], 3) : [] as $licencie) {
            $notifications[] = [
                'key'  => 'licencie:' . $licencie->getId(),
                'icon' => 'fa-id-card',
                'text' => sprintf('Nouveau licencié : %s %s', $licencie->getPrenom(), $licencie->getNom()),
                'meta' => 'Licencié',
                'url'  => '/admin/licencies/' . $licencie->getId(),
            ];
        }

        if ($wants('utilisateurs') && $this->permissions->can('utilisateur.voir')) {
            $latest = $this->userRepository->createQueryBuilder('u')
                ->where('u.anonymizedAt IS NULL')
                ->andWhere("u.roles NOT LIKE '%ROLE_EDITOR%' AND u.roles NOT LIKE '%ROLE_ADMIN%' AND u.roles NOT LIKE '%ROLE_DEV%'")
                ->orderBy('u.id', SortDirection::Descending)
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();
            foreach ($latest as $account) {
                $notifications[] = [
                    'key'  => 'compte:' . $account->getId(),
                    'icon' => 'fa-user-plus',
                    'text' => sprintf('Nouveau compte : %s', $account->getNomComplet() ?: $account->getEmail()),
                    'meta' => 'Compte utilisateur',
                    'url'  => $this->urls->generate('admin_user_index'),
                ];
            }
        }

        return $notifications;
    }
}

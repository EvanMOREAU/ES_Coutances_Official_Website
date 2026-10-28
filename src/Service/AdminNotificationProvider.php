<?php

namespace App\Service;

use App\Entity\ArticleVariante;
use App\Entity\NotificationState;
use App\Entity\User;
use App\Repository\AdhesionRepository;
use App\Repository\ArticleVarianteRepository;
use App\Repository\BoutiqueSettingsRepository;
use App\Repository\CommandeRepository;
use App\Repository\ContratPartenaireRepository;
use App\Repository\EntrainementRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Repository\NotificationStateRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Service\Chat\ChatService;
use App\Service\Deploy\UpdateWatcher;
use App\Service\Notification\NotificationPreferences;
use Doctrine\ORM\EntityManagerInterface;
use SortDirection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifications de la clochette du back-office. Elles sont calculées à la
 * volée à partir des données existantes (activité récente, points d'attention,
 * commandes à préparer) et chacune porte une clé stable. Ce que l'utilisateur
 * en fait — lue, masquée — est mémorisé par clé dans NotificationState.
 *
 * Une notification masquée ne réapparaît pas ; une notification dont le
 * contenu change (ex. le nombre de conseils à compléter) reçoit une nouvelle
 * clé et redevient donc non lue.
 *
 * Chaque rubrique peut être désactivée par l'utilisateur (NotificationPreferences) ; les textes
 * sont traduits dans la langue du compte (clés « notif.bell.* »).
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
        private readonly UpdateWatcher $updates,
        private readonly ArticleVarianteRepository $varianteRepository,
        private readonly BoutiqueSettingsRepository $boutiqueSettings,
        private readonly TranslatorInterface $translator,
        private readonly AdhesionRepository $adhesionRepository,
        private readonly ContratPartenaireRepository $contratRepository,
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

    /** @param array<string, scalar> $params */
    private function t(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params);
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
                    'text' => $this->t('notif.bell.messages', ['%count%' => $unread]),
                    'meta' => $this->t('notif.bell.messages_meta'),
                    'url'  => $this->urls->generate('admin_chat_index'),
                ];
            }
        }

        // Mise à jour du site disponible (développeurs) ; la clé change avec le nombre, une nouvelle version redevient non lue.
        if ($me instanceof User && $this->security->isGranted('ROLE_DEV') && $wants('systeme')) {
            $pending = $this->updates->pendingCount();
            if ($pending > 0) {
                $notifications[] = [
                    'key'  => 'maj:'.$pending,
                    'icon' => 'fa-rocket',
                    'text' => $this->t('notif.bell.update', ['%count%' => $pending]),
                    'meta' => $this->t('notif.bell.update_meta'),
                    'url'  => $this->urls->generate('admin_deploy_index'),
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
                    'text' => $this->t('notif.bell.planning', ['%title%' => $event->getTitre(), '%date%' => (string) $event->getDate()?->format('d/m'), '%time%' => (string) $event->getHeureDebut()?->format('H:i')]),
                    'meta' => $this->t('notif.bell.planning_meta'),
                    'url'  => $this->urls->generate('admin_planning_index'),
                ];
            }
        }

        // Commandes de la boutique à préparer.
        foreach ($wants('commandes') && $this->permissions->can('commande.voir') ? $this->commandeRepository->findAPreparer(5) : [] as $commande) {
            $notifications[] = [
                'key'  => 'commande:'.$commande->getId(),
                'icon' => 'fa-bag-shopping',
                'text' => $this->t('notif.bell.order', ['%ref%' => (string) $commande->getReference(), '%name%' => $commande->getNomComplet()]),
                'meta' => $this->t($commande->isPayee() ? 'notif.bell.order_meta_paid' : 'notif.bell.order_meta_pending', ['%total%' => Money::format($commande->getTotalCentimes())]),
                'url'  => $this->urls->generate('admin_commande_show', ['id' => $commande->getId()]),
            ];
        }

        // Stock bas : une seule notification pour toute la boutique ; sa clé change avec les tailles concernées, elle redevient non lue.
        if ($wants('stock') && $this->permissions->can('article.voir')) {
            $basses = $this->varianteRepository->findStockBas($this->boutiqueSettings->getSingleton()?->getSeuilStockBas() ?? 3, 10);
            if ([] !== $basses) {
                $noms = array_map(static fn (ArticleVariante $v): string => sprintf('%s (%s) : %d', $v->getArticle()?->getNom(), $v->getLibelle(), $v->getStock()), $basses);
                $notifications[] = [
                    'key'  => 'stock:'.substr(md5(implode('|', $noms)), 0, 10),
                    'icon' => 'fa-box-open',
                    'text' => $this->t('notif.bell.stock', ['%count%' => count($basses)]),
                    'meta' => implode(' · ', array_slice($noms, 0, 3)),
                    'url'  => $this->urls->generate('admin_article_index'),
                ];
            }
        }

        // Licences récemment enregistrées (paiements des licences).
        foreach ($wants('adhesions') && $this->permissions->can('adhesion.voir') ? $this->adhesionRepository->findBy([], ['id' => 'DESC'], 3) : [] as $adhesion) {
            $notifications[] = [
                'key'  => 'adhesion:'.$adhesion->getId(),
                'icon' => 'fa-file-invoice-dollar',
                'text' => $this->t('notif.bell.adhesion', ['%name%' => $adhesion->getLicencieAffichage(), '%season%' => (string) $adhesion->getSaison()?->getLibelle()]),
                'meta' => $this->t('notif.bell.adhesion_meta', ['%status%' => $adhesion->getStatutPaiementLabel()]),
                'url'  => $this->urls->generate('admin_adhesion_index'),
            ];
        }

        // Tâches de contrats partenaires en retard ou à faire cette semaine.
        if ($wants('partenaires') && $this->permissions->can('partenaire.voir')) {
            $taches = $this->contratRepository->tachesATraiter();
            if ($taches['late'] + $taches['soon'] > 0) {
                $notifications[] = [
                    'key'  => sprintf('taches:%d:%d', $taches['late'], $taches['soon']),
                    'icon' => 'fa-list-check',
                    'text' => $this->t('notif.bell.tasks', ['%count%' => $taches['late'] + $taches['soon']]),
                    'meta' => $this->t('notif.bell.tasks_meta', ['%late%' => $taches['late'], '%soon%' => $taches['soon']]),
                    'url'  => $this->urls->generate('admin_partenaire_index'),
                ];
            }
        }

        // Conseils de configuration : simples invitations à compléter le site, jamais bloquantes.
        $hubs = [
            SiteAdvisor::HUB_VITRINE    => ['vitrine', 'notif.bell.hub_vitrine', '/admin/site-vitrine'],
            SiteAdvisor::HUB_LICENCIES  => ['licencies', 'notif.bell.hub_licencies', '/admin/gestion-licencies'],
            SiteAdvisor::HUB_PARTENAIRE => ['partenaire', 'notif.bell.hub_partenaires', '/admin/partenaires/hub'],
        ];
        $hubPermission = [
            SiteAdvisor::HUB_VITRINE    => 'rejoindre_card.voir,offre_emploi.voir,slide_carousel.voir,page_contenu.voir,membre.voir,reglages.voir',
            SiteAdvisor::HUB_LICENCIES  => 'famille.voir,licencie.voir,equipe.voir,saison.voir',
            SiteAdvisor::HUB_PARTENAIRE => 'partenaire.voir',
        ];
        foreach ($hubs as $hub => [$slug, $labelKey, $url]) {
            if (!$wants('conseils') || !$this->permissions->can($hubPermission[$hub])) {
                continue;
            }
            $count = $this->advisor->count($hub);
            if ($count > 0) {
                $notifications[] = [
                    'key'  => sprintf('conseil:%s:%d', $slug, $count),
                    'icon' => 'fa-lightbulb',
                    'text' => $this->t('notif.bell.advice', ['%hub%' => $this->t($labelKey), '%count%' => $count]),
                    'meta' => $this->t('notif.bell.advice_meta'),
                    'url'  => $url,
                ];
            }
        }

        // Familles sans email exploitable (adresse invalide, ou provisoire générée par un import) :
        // la clé inclut le compte pour que la notification réapparaisse si elle a été masquée puis
        // que le nombre de familles concernées change à nouveau.
        if ($wants('conseils') && $this->permissions->can('famille.voir')) {
            $count = $this->familleRepository->countSansEmailValide();
            if ($count > 0) {
                $notifications[] = [
                    'key'  => sprintf('email-invalide:%d', $count),
                    'icon' => 'fa-envelope-circle-exclamation',
                    'text' => $this->t('notif.bell.invalid_email', ['%count%' => $count]),
                    'meta' => $this->t('notif.bell.invalid_email_meta'),
                    'url'  => $this->urls->generate('admin_famille_index'),
                ];
            }
        }

        foreach ($wants('familles') && $this->permissions->can('famille.voir') ? $this->familleRepository->findBy([], ['id' => 'DESC'], 3) : [] as $famille) {
            $notifications[] = [
                'key'  => 'famille:'.$famille->getId(),
                'icon' => 'fa-house-user',
                'text' => $this->t('notif.bell.family', ['%name%' => (string) $famille->getNom()]),
                'meta' => $this->t('notif.bell.family_meta'),
                'url'  => '/admin/familles/'.$famille->getId(),
            ];
        }

        foreach ($wants('licencies') && $this->permissions->can('licencie.voir') ? $this->licencieRepository->findBy([], ['id' => 'DESC'], 3) : [] as $licencie) {
            $notifications[] = [
                'key'  => 'licencie:'.$licencie->getId(),
                'icon' => 'fa-id-card',
                'text' => $this->t('notif.bell.member', ['%name%' => $licencie->getPrenom().' '.$licencie->getNom()]),
                'meta' => $this->t('notif.bell.member_meta'),
                'url'  => '/admin/licencies/'.$licencie->getId(),
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
                    'key'  => 'compte:'.$account->getId(),
                    'icon' => 'fa-user-plus',
                    'text' => $this->t('notif.bell.account', ['%name%' => $account->getNomComplet() ?: (string) $account->getEmail()]),
                    'meta' => $this->t('notif.bell.account_meta'),
                    'url'  => $this->urls->generate('admin_user_index'),
                ];
            }
        }

        return $notifications;
    }
}

<?php

namespace App\Service;

use App\Entity\Famille;
use App\Entity\NotificationState;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\FamilleRepository;
use App\Repository\NotificationStateRepository;
use App\Security\PermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
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

        // Commandes de la boutique à préparer.
        foreach ($this->permissions->can('commande.voir') ? $this->commandeRepository->findAPreparer(5) : [] as $commande) {
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
            SiteAdvisor::HUB_VITRINE   => ['vitrine', 'Site vitrine', '/admin/site-vitrine'],
            SiteAdvisor::HUB_LICENCIES => ['licencies', 'Gestion des licenciés', '/admin/gestion-licencies'],
        ];
        $hubPermission = [
            SiteAdvisor::HUB_VITRINE   => 'partenaire.voir,rejoindre_card.voir,offre_emploi.voir,slide_carousel.voir,page_contenu.voir,membre.voir,reglages.voir',
            SiteAdvisor::HUB_LICENCIES => 'famille.voir,licencie.voir,equipe.voir,saison.voir',
        ];
        foreach ($hubs as $hub => [$slug, $label, $url]) {
            if (!$this->permissions->can($hubPermission[$hub])) {
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
        if ($this->permissions->can('famille.voir')) {
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

        foreach ($this->permissions->can('famille.voir') ? $this->familleRepository->findBy([], ['id' => 'DESC'], 3) : [] as $famille) {
            $notifications[] = [
                'key'  => 'famille:' . $famille->getId(),
                'icon' => 'fa-house-user',
                'text' => sprintf('Nouvelle famille : %s', $famille->getNom()),
                'meta' => 'Famille',
                'url'  => '/admin/familles/' . $famille->getId(),
            ];
        }

        return $notifications;
    }
}

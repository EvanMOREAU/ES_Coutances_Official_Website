<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Service\AdminNotificationProvider;
use App\Service\ChangelogFile;
use App\Service\SiteAdvisor;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminNotificationsExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminNotificationProvider $notificationProvider,
        private readonly SiteAdvisor $advisor,
        private readonly CommandeRepository $commandes,
        private readonly ChangelogFile $changelog,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_notifications', [$this->notificationProvider, 'getNotifications']),
            // Pastille « commandes à préparer » du menu Boutique.
            new TwigFunction('commandes_a_preparer', [$this->commandes, 'countAPreparer']),
            new TwigFunction('admin_notifications_unread', [$this->notificationProvider, 'unreadCount']),
            // Conseils de configuration (voir SiteAdvisor) : advice_items('vitrine'|'licencies'), advice_count(...)
            new TwigFunction('advice_items', [$this->advisor, 'items']),
            new TwigFunction('advice_count', [$this->advisor, 'count']),
            // Pastille « nouvelle version » du menu Changelog : 1 si la dernière version n'a pas été vue.
            new TwigFunction('changelog_non_lu', $this->changelogNonLu(...)),
        ];
    }

    public function changelogNonLu(): int
    {
        $user   = $this->security->getUser();
        $latest = $this->changelog->releases()[0] ?? null;

        return $user instanceof User && $latest && $user->getChangelogVersionVue() !== $latest['version'] ? 1 : 0;
    }
}

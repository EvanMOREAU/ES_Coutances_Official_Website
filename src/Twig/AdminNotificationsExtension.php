<?php

namespace App\Twig;

use App\Repository\CommandeRepository;
use App\Service\AdminNotificationProvider;
use App\Service\SiteAdvisor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminNotificationsExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminNotificationProvider $notificationProvider,
        private readonly SiteAdvisor $advisor,
        private readonly CommandeRepository $commandes,
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
        ];
    }
}

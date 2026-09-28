<?php

namespace App\EventListener;

use App\Controller\BoutiqueController;
use App\Repository\BoutiqueSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Quand la boutique est en maintenance (réglage admin, distinct du mode
 * maintenance fichier de tout le site), remplace les pages de la boutique
 * publique par une page d'indisponibilité — sauf pour les comptes ROLE_DEV,
 * qui continuent de voir la boutique (avec un bandeau d'avertissement, voir
 * boutique/_layout.html.twig).
 */
class BoutiqueMaintenanceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly BoutiqueSettingsRepository $settings,
        private readonly Security $security,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();
        $instance   = is_array($controller) ? $controller[0] : null;
        if (!$instance instanceof BoutiqueController) {
            return;
        }

        if ($this->security->isGranted('ROLE_DEV')) {
            return;
        }

        if (!$this->settings->getSingleton()?->isEnMaintenance()) {
            return;
        }

        $event->setController(fn () => new Response(
            $this->twig->render('boutique/maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
        ));
    }
}

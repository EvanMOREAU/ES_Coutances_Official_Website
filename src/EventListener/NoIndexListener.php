<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Interdit l'indexation des pages privées ou sans intérêt pour un moteur de recherche (back-office,
 * espace connecté, panier, suivi de commande…), en complément du robots.txt qui n'empêche pas à lui seul
 * l'affichage d'une adresse déjà connue.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class NoIndexListener
{
    private const PREFIXES = ['/admin', '/mon-compte', '/reset-password', '/boutique/panier', '/boutique/commande', '/boutique/code-promo'];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $event->getResponse()->headers->set('X-Robots-Tag', 'noindex');

                return;
            }
        }
    }
}

<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute à chaque réponse les en-têtes de sécurité usuels du navigateur : pas de détection
 * de type MIME, pas d'inclusion du site dans un cadre d'un autre domaine, référent limité,
 * fonctions matérielles inutiles désactivées, et HSTS dès que la connexion est en HTTPS.
 *
 * Une valeur déjà posée par un contrôleur est conservée.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -128)]
final class SecurityHeadersListener
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if ($event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}

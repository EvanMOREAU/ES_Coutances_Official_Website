<?php

namespace App\EventListener;

use App\Security\CspNonce;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute à chaque réponse les en-têtes de sécurité usuels du navigateur : pas de détection
 * de type MIME, pas d'inclusion du site dans un cadre d'un autre domaine, référent limité,
 * fonctions matérielles inutiles désactivées, Content-Security-Policy (scripts limités au site et
 * à ceux portant le nonce de la requête) et HSTS dès que la connexion est en HTTPS.
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

    /**
     * Les feuilles de style en ligne restent permises (nombreux attributs style et blocs <style>
     * dans les gabarits) ; les scripts, eux, ne s'exécutent que s'ils viennent du site ou portent le nonce.
     * Les domaines HelloAsso sont ceux vers lesquels le client est redirigé pour payer.
     */
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'nonce-%s'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "media-src 'self' blob:",
        "object-src 'self'",
        "frame-src 'self' https://www.openstreetmap.org",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self' https://*.helloasso.com https://*.helloasso-sandbox.com",
    ];

    public function __construct(private readonly CspNonce $nonce)
    {
    }

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

        if (!$headers->has('Content-Security-Policy') && !$this->isProfilerRoute($event)) {
            $headers->set('Content-Security-Policy', sprintf(implode('; ', self::CSP), $this->nonce->value()));
        }

        if ($event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }

    /** La barre de débogage et le profileur (dev) injectent leurs propres scripts. */
    private function isProfilerRoute(ResponseEvent $event): bool
    {
        return str_starts_with((string) $event->getRequest()->attributes->get('_route'), '_');
    }
}

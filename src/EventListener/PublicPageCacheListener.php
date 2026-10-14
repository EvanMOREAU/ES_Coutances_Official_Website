<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cache HTTP des pages publiques identiques pour tous les visiteurs (navigateur et éventuel proxy / CDN).
 *
 * Le pare-feu de sécurité lit le jeton à chaque requête (double authentification), ce qui fait considérer la
 * session comme « utilisée » : Symfony rendrait alors toute réponse privée (`max-age=0, private`). Pour un
 * visiteur anonyme, cette lecture ne crée pourtant aucune session : on le signale (en-tête interne
 * Symfony-Session-NoAutoCacheControl) pour que les en-têtes de cache posés ici soient conservés.
 *
 * Une page n'est rendue publique que si le visiteur n'envoie aucun cookie, qu'aucune session n'a réellement été
 * démarrée par la page (compte connecté, panier, jeton CSRF) et que la réponse ne pose aucun cookie : sinon elle
 * reste privée, comme avant.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -64)]
final class PublicPageCacheListener
{
    /** Pages publiques et leur durée de cache, en secondes. */
    private const ROUTES = [
        'app_home'                  => 300,
        'app_histoire'              => 900,
        'app_encadrement'           => 900,
        'app_infrastructure'        => 900,
        'app_page_detail'           => 900,
        'app_legal_mentions'        => 3600,
        'app_legal_confidentialite' => 3600,
        'app_legal_cgv'             => 3600,
        'app_legal_cookies'         => 3600,
        'boutique_index'            => 300,
        'boutique_article'          => 300,
    ];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request  = $event->getRequest();
        $response = $event->getResponse();
        $ttl      = self::ROUTES[(string) $request->attributes->get('_route')] ?? null;

        if (null === $ttl || !$request->isMethodCacheable() || 200 !== $response->getStatusCode()
            || $request->cookies->count() > 0 || [] !== $response->headers->getCookies()) {
            return;
        }
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge($ttl);
        $response->setSharedMaxAge($ttl);
        $response->headers->set('Vary', 'Cookie', false);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }
}

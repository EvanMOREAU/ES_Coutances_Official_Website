<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Freine les envois répétés (spam, énumération de comptes, bourrage de panier) sur les
 * formulaires publics : chaque route sensible est rattachée à un limiteur défini dans
 * config/packages/framework.yaml, avec un compteur par adresse IP. Seules les requêtes POST
 * sont comptées ; une fois la limite atteinte, la réponse est un 429 avec l'en-tête Retry-After.
 *
 * La connexion est protégée séparément par `login_throttling` (security.yaml).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final class PublicFormThrottleListener
{
    /** @var array<string, RateLimiterFactoryInterface> route => limiteur */
    private array $limiters;

    public function __construct(
        #[Autowire(service: 'limiter.public_contact')] RateLimiterFactoryInterface $contact,
        #[Autowire(service: 'limiter.public_registration')] RateLimiterFactoryInterface $registration,
        #[Autowire(service: 'limiter.public_reset_password')] RateLimiterFactoryInterface $resetPassword,
        #[Autowire(service: 'limiter.public_cart')] RateLimiterFactoryInterface $cart,
        #[Autowire(service: 'limiter.public_webhook')] RateLimiterFactoryInterface $webhook,
    ) {
        $this->limiters = [
            'app_contact' => $contact,
            'portail_inscription' => $registration,
            'app_forgot_password_request' => $resetPassword,
            'boutique_panier_ajouter' => $cart,
            'boutique_panier_modifier' => $cart,
            'boutique_panier_retirer' => $cart,
            'boutique_commande' => $cart,
            'boutique_commande_livraison' => $cart,
            'boutique_helloasso_notification' => $webhook,
        ];
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST')) {
            return;
        }

        $limiter = $this->limiters[$request->attributes->get('_route')] ?? null;
        if (null === $limiter) {
            return;
        }

        $limit = $limiter->create($request->getClientIp() ?? 'anonyme')->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw new TooManyRequestsHttpException($retryAfter, 'Trop de tentatives. Merci de réessayer dans quelques minutes.', null, Response::HTTP_TOO_MANY_REQUESTS);
        }
    }
}

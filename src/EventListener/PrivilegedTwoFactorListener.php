<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Les comptes qui peuvent tout modifier (développeur, administrateur — dont le déploiement du site
 * depuis l'admin) doivent protéger leur connexion : tant qu'aucune seconde protection (application
 * d'authentification, code par e-mail ou clé d'accès) n'est activée, toutes les pages du
 * back-office renvoient vers « Paramètres > Sécurité », où elle se configure.
 *
 * Désactivable avec ENFORCE_PRIVILEGED_2FA=0 (utilisé par les tests).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final class PrivilegedTwoFactorListener
{
    private const PRIVILEGED_ROLES = ['ROLE_DEV', 'ROLE_ADMIN'];

    public function __construct(
        private readonly Security $security,
        private readonly WebauthnCredentialRepository $passkeys,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%env(bool:ENFORCE_PRIVILEGED_2FA)%')] private readonly bool $enforced,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route');
        if (!$this->enforced || !$event->isMainRequest() || !str_starts_with($route, 'admin') || str_starts_with($route, 'admin_parametres')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || [] === array_intersect(self::PRIVILEGED_ROLES, $user->getRoles()) || $this->isProtected($user)) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'Votre compte dispose de droits étendus : activez une double authentification (application, code par e-mail ou clé d\'accès) pour continuer.');
        }

        $event->setResponse(new RedirectResponse($this->urls->generate('admin_parametres_securite')));
    }

    private function isProtected(User $user): bool
    {
        return $user->hasTwoFactorEnabled() || [] !== $this->passkeys->findAllForUser($user);
    }
}

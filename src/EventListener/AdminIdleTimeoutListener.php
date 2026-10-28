<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Déconnecte un membre de l'équipe (back-office) après ADMIN_IDLE_TIMEOUT secondes sans action : un poste laissé
 * ouvert ne reste pas connecté indéfiniment. Les interrogations automatiques de la page (pastilles, messagerie)
 * ne comptent pas comme une action : elles vérifient le délai sans le prolonger. 0 désactive la déconnexion.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final class AdminIdleTimeoutListener
{
    private const SESSION_KEY = 'admin_last_activity';

    /** Routes interrogées en arrière-plan par les pages du back-office. */
    private const BACKGROUND_ROUTES = ['admin_chat_sync', 'admin_chat_unread', 'admin_deploy_pending', 'admin_notification_index'];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%env(int:ADMIN_IDLE_TIMEOUT)%')] private readonly int $timeout,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route');
        if ($this->timeout <= 0 || !$event->isMainRequest() || !str_starts_with($route, 'admin') || !$request->hasSession()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isStaff()) {
            return;
        }

        $session = $request->getSession();
        $last    = $session->get(self::SESSION_KEY);
        $now     = time();

        if (\is_int($last) && $now - $last > $this->timeout) {
            $session->remove(self::SESSION_KEY);
            $this->security->logout(false);
            // Nouvelle session après la déconnexion : le message d'explication y est déposé.
            $flashes = $request->getSession();
            if ($flashes instanceof FlashBagAwareSessionInterface) {
                $flashes->getFlashBag()->add('error', 'Vous avez été déconnecté après une période d\'inactivité. Reconnectez-vous pour continuer.');
            }
            $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));

            return;
        }

        if (!\in_array($route, self::BACKGROUND_ROUTES, true) || !\is_int($last)) {
            $session->set(self::SESSION_KEY, $now);
        }
    }
}

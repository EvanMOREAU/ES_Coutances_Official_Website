<?php

namespace App\EventListener;

use App\Entity\User;
use App\Security\PermissionChecker;
use App\Security\RoutePermissions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applique les autorisations à chaque page du back-office : la route demandée est associée à
 * l'autorisation qu'elle exige (RoutePermissions) puis comparée à celles de l'utilisateur.
 * Passe après le pare-feu (priorité 7 < 8) : l'utilisateur est déjà identifié.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final class PermissionListener
{
    public function __construct(
        private readonly Security $security,
        private readonly PermissionChecker $permissions,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || !str_starts_with($route, 'admin')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || 'app_login' === $route) {
            return; // non connecté : le pare-feu s'en charge
        }

        $required = RoutePermissions::required($route, $request->getMethod(), (array) $request->attributes->get('_route_params', []), $request);
        if (null === $required) {
            return;
        }

        if (false === $required || !$this->permissions->can($required, $user)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}

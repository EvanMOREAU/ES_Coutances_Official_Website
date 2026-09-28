<?php

namespace App\Twig;

use App\Security\PermissionCatalog;
use App\Security\PermissionChecker;
use App\Security\RoutePermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PermissionExtension extends AbstractExtension
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly RouterInterface $router,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // {% if can('famille.creer') %} ; « a,b » : l'une des deux suffit.
            new TwigFunction('can', $this->permissions->can(...)),
            // {% if can_url(path('admin_famille_edit', {id: 1})) %} ; méthode POST pour un lien d'action.
            new TwigFunction('can_url', $this->canUrl(...)),
            // Catalogue des autorisations, par rubrique (matrice des profils et des utilisateurs).
            new TwigFunction('permission_groups', PermissionCatalog::groups(...)),
        ];
    }

    /** L'utilisateur peut-il ouvrir cette adresse du back-office ? (masque les boutons qu'il ne peut pas utiliser) */
    public function canUrl(?string $url, string $method = 'GET'): bool
    {
        if (!$url) {
            return false;
        }

        try {
            $path    = parse_url($url, PHP_URL_PATH) ?: $url;
            $matched = $this->router->matchRequest(Request::create($path, $method));
        } catch (ExceptionInterface) {
            return false;
        }

        $route = (string) ($matched['_route'] ?? '');
        if (!str_starts_with($route, 'admin')) {
            return true;
        }
        $required = RoutePermissions::required($route, $method, $matched);

        return null === $required || (false !== $required && $this->permissions->can($required));
    }
}

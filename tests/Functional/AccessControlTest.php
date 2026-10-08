<?php

namespace App\Tests\Functional;

use App\Security\RoutePermissions;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Parcourt TOUTES les routes de l'application : aucune page du back-office ni de l'espace
 * familles ne doit s'ouvrir sans la bonne connexion, et aucune ne doit planter (HTTP 5xx).
 */
final class AccessControlTest extends DatabaseTestCase
{
    /** Routes d'authentification, volontairement accessibles sans connexion complète. */
    private const AUTH_ROUTES = [
        'app_login', 'app_logout', '2fa_login', '2fa_login_check', '2fa_resend_email',
        'admin_webauthn_login_options', 'admin_webauthn_login_check',
        'portail_login', 'portail_logout', 'portail_inscription', '2fa_login', 'portail_2fa_login',
        'portail_2fa_login_check', 'portail_2fa_resend_email',
        'portail_webauthn_login_options', 'portail_webauthn_login_check',
    ];

    /** @return array<string, Route> */
    private function routes(string $prefix): array
    {
        $routes = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (str_starts_with($route->getPath(), $prefix) && !\in_array($name, self::AUTH_ROUTES, true)) {
                $routes[$name] = $route;
            }
        }
        ksort($routes);

        return $routes;
    }

    private function urlFor(Route $route): string
    {
        $values = ['type' => 'famille', 'action' => 'payer', 'key' => 'liste', 'token' => 'abc', 'reference' => 'REF', 'slug' => 'inconnu', 'file' => 'manuel-20260101-000000.sql.gz'];

        return preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($values, $route): string {
            $requirement = $route->getRequirement($m[1]);
            if (isset($values[$m[1]]) && (null === $requirement || preg_match('#^'.$requirement.'$#', $values[$m[1]]))) {
                return $values[$m[1]];
            }

            return '999999';
        }, $route->getPath());
    }

    private function methodOf(Route $route): string
    {
        return $route->getMethods()[0] ?? 'GET';
    }

    public function testAnyUnknownAdminRouteWouldBeDeniedByDefault(): void
    {
        $unmapped = [];
        foreach ($this->routes('/admin') as $name => $route) {
            if (!str_starts_with($name, 'admin')) {
                continue;
            }
            if (false === RoutePermissions::required($name, $this->methodOf($route), ['type' => 'famille', 'action' => 'payer'])) {
                $unmapped[] = $name;
            }
        }

        self::assertSame([], $unmapped, 'Ces routes du back-office n\'ont aucune autorisation associée dans RoutePermissions.');
    }

    public function testAnonymousVisitorIsRedirectedToTheAdminLogin(): void
    {
        $failures = [];
        foreach ($this->routes('/admin') as $name => $route) {
            $this->client->request($this->methodOf($route), $this->urlFor($route));
            $response = $this->client->getResponse();
            if (!$response->isRedirect() || !str_contains((string) $response->headers->get('Location'), '/admin/login')) {
                $failures[] = sprintf('%s (%s) → HTTP %d', $name, $this->urlFor($route), $response->getStatusCode());
            }
        }

        self::assertSame([], $failures);
    }

    public function testAnonymousVisitorIsRedirectedFromThePortal(): void
    {
        $failures = [];
        foreach ($this->routes('/mon-compte') as $name => $route) {
            $this->client->request($this->methodOf($route), $this->urlFor($route));
            $response = $this->client->getResponse();
            if (!$response->isRedirect() || !str_contains((string) $response->headers->get('Location'), '/mon-compte/connexion')) {
                $failures[] = sprintf('%s (%s) → HTTP %d', $name, $this->urlFor($route), $response->getStatusCode());
            }
        }

        self::assertSame([], $failures);
    }

    public function testPortalAccountCannotEnterTheAdmin(): void
    {
        $this->loginAs(['ROLE_FAMILLE']);

        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorWithoutProfileIsDeniedEverywhereExceptFreeRoutes(): void
    {
        $this->loginAs(['ROLE_EDITOR']);

        $failures = [];
        foreach ($this->routes('/admin') as $name => $route) {
            $method = $this->methodOf($route);
            $required = RoutePermissions::required($name, $method, ['type' => 'famille', 'action' => 'payer']);
            $this->client->request($method, $this->urlFor($route));
            $status = $this->client->getResponse()->getStatusCode();

            if (null !== $required && 403 !== $status) {
                $failures[] = sprintf('%s exige « %s » mais répond HTTP %d à un éditeur sans autorisation', $name, $required, $status);
            }
            if ($status >= 500) {
                $failures[] = sprintf('%s → HTTP %d', $name, $status);
            }
        }

        self::assertSame([], $failures);
    }

    public function testDeveloperNeverGetsAServerError(): void
    {
        $this->loginAsDev();

        $failures = [];
        foreach ($this->routes('/admin') as $name => $route) {
            $method = $this->methodOf($route);
            if ('GET' !== $method) {
                continue; // les écritures sont couvertes par des tests dédiés (CSRF, jeux de données)
            }
            $this->client->request('GET', $this->urlFor($route));
            $status = $this->client->getResponse()->getStatusCode();
            if ($status >= 500) {
                $failures[] = sprintf('%s (%s) → HTTP %d', $name, $this->urlFor($route), $status);
            }
        }

        self::assertSame([], $failures);
    }
}

<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Soumet à vide chaque formulaire de création du back-office : une validation oubliée se
 * traduit par une erreur SQL (HTTP 500) au lieu d'un message pour l'utilisateur.
 */
final class EmptyFormsTest extends DatabaseTestCase
{
    public function testSubmittingEveryCreationFormEmptyNeverCrashes(): void
    {
        $this->loginAsDev();

        $failures = [];
        $tested = 0;
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (!str_starts_with($name, 'admin_') || !str_ends_with($name, '_new') || str_contains($route->getPath(), '{')) {
                continue;
            }
            if (!\in_array('GET', $route->getMethods(), true) || !\in_array('POST', $route->getMethods(), true)) {
                continue;
            }

            $crawler = $this->client->request('GET', $route->getPath());
            if (!$this->client->getResponse()->isSuccessful()) {
                continue;
            }
            $forms = $crawler->filter('main form[method=post], form[method=post]')->reduce(
                static fn ($node) => !str_contains((string) $node->attr('action'), '/logout') && $node->filter('input[name=_token], input[type=file], textarea, input[type=text]')->count() > 0,
            );
            if (0 === $forms->count()) {
                continue;
            }

            $form = $forms->first()->form();
            $values = $form->getPhpValues();
            $this->blank($values);
            $this->client->request('POST', $form->getUri(), $values, $form->getPhpFiles());
            ++$tested;

            if ($this->client->getResponse()->getStatusCode() >= 500) {
                $failures[] = sprintf('%s → HTTP %d : %s', $name, $this->client->getResponse()->getStatusCode(), rawurldecode((string) $this->client->getResponse()->headers->get('X-Debug-Exception')));
            }
            // Une base qui se met en erreur (« exception ») ne doit jamais fuiter dans la page.
            $this->em->clear();
        }

        self::assertGreaterThan(5, $tested, 'Le test doit réellement parcourir des formulaires.');
        self::assertSame([], $failures, 'Ces formulaires plantent quand on les envoie vides (validation manquante).');
    }

    /** Vide tous les champs texte sauf le jeton CSRF. @param array<string, mixed> $values */
    private function blank(array &$values): void
    {
        foreach ($values as $key => &$value) {
            if (\is_array($value)) {
                $this->blank($value);
            } elseif (!str_contains((string) $key, 'token') && !str_starts_with((string) $key, '_')) {
                $value = '';
            }
        }
    }
}

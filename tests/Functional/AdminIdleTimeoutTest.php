<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;

/** Déconnexion du back-office après une période d'inactivité (ADMIN_IDLE_TIMEOUT, 3600 s par défaut). */
final class AdminIdleTimeoutTest extends DatabaseTestCase
{
    public function testActivityKeepsTheSessionAliveAndInactivityEndsIt(): void
    {
        $this->loginAsDev();

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        // Simule une pause de plus d'une heure : le prochain clic renvoie vers la connexion.
        $session = $this->client->getRequest()->getSession();
        $session->set('admin_last_activity', time() - 7200);
        $session->save();

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects();
        self::assertStringContainsString('login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testBackgroundPollingDoesNotExtendTheSession(): void
    {
        $this->loginAsDev();
        $this->client->request('GET', '/admin');
        $session = $this->client->getRequest()->getSession();
        $old = time() - 600;
        $session->set('admin_last_activity', $old);
        $session->save();

        $this->client->request('GET', '/admin/mise-a-jour/etat');
        self::assertResponseIsSuccessful();

        self::assertSame($old, $this->client->getRequest()->getSession()->get('admin_last_activity'));
    }
}

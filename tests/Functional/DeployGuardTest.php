<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;

/**
 * Mise à jour du site : rôle développeur uniquement, seconde authentification obligatoire, mot de passe
 * ressaisi avant chaque lancement.
 */
final class DeployGuardTest extends DatabaseTestCase
{
    private function csrf(): string
    {
        $crawler = $this->client->request('GET', '/admin/mise-a-jour');
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('[data-controller=deploy]')->attr('data-deploy-token-value');
    }

    public function testAdministratorsAndEditorsCannotUseTheUpdateScreen(): void
    {
        foreach (['ROLE_ADMIN', 'ROLE_EDITOR'] as $role) {
            $this->loginAs([$role]);
            foreach (['/admin/mise-a-jour', '/admin/mise-a-jour/etat'] as $url) {
                $this->client->request('GET', $url);
                self::assertResponseStatusCodeSame(403, $role.' '.$url);
            }
            $this->client->request('POST', '/admin/mise-a-jour/lancer', server: ['HTTP_X_CSRF_TOKEN' => 'x']);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testStartingRequiresASecondFactorOnTheAccount(): void
    {
        $this->loginAsDev();
        $token = $this->csrf();

        $this->client->request('POST', '/admin/mise-a-jour/lancer', server: ['HTTP_X_CSRF_TOKEN' => $token]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('double authentification', (string) $this->client->getResponse()->getContent());
    }

    public function testStartingRequiresTheCurrentPasswordThenIsSingleUse(): void
    {
        $dev = $this->loginAsDev();
        $dev->setEmailAuthEnabled(true);
        $this->em->flush();
        $token = $this->csrf();
        $headers = ['HTTP_X_CSRF_TOKEN' => $token];

        // Sans ressaisie : refusé, le client est invité à redemander le mot de passe.
        $this->client->request('POST', '/admin/mise-a-jour/lancer', server: $headers);
        self::assertResponseStatusCodeSame(403);
        self::assertTrue(json_decode((string) $this->client->getResponse()->getContent(), true)['reauth']);

        // Mauvais mot de passe.
        $this->client->request('POST', '/admin/mise-a-jour/reauthentification', ['password' => 'faux'], server: $headers);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/mise-a-jour/lancer', server: $headers);
        self::assertResponseStatusCodeSame(403);

        // Bon mot de passe : les gardes sont franchies, une seule fois (409 : DEPLOY_FROM_ADMIN=0 en test, aucun processus lancé).
        $this->client->request('POST', '/admin/mise-a-jour/reauthentification', ['password' => self::PASSWORD], server: $headers);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/admin/mise-a-jour/lancer', server: $headers);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('POST', '/admin/mise-a-jour/lancer', server: $headers);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPasswordCheckIsRateLimited(): void
    {
        $dev = $this->loginAsDev();
        $dev->setEmailAuthEnabled(true);
        $this->em->flush();
        $headers = ['HTTP_X_CSRF_TOKEN' => $this->csrf()];

        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('POST', '/admin/mise-a-jour/reauthentification', ['password' => 'faux'], server: $headers);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/admin/mise-a-jour/reauthentification', ['password' => self::PASSWORD], server: $headers);
        self::assertResponseStatusCodeSame(429);
    }
}

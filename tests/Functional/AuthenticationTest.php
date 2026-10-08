<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;

final class AuthenticationTest extends DatabaseTestCase
{
    private function submitLogin(string $url, string $email, string $password): void
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[method=post]')->first()->form(['email' => $email, 'password' => $password]);
        $this->client->submit($form);
    }

    public function testStaffCanLogInToTheAdmin(): void
    {
        $user = $this->createUser(['ROLE_EDITOR']);

        $this->submitLogin('/admin/login', (string) $user->getEmail(), self::PASSWORD);

        self::assertResponseRedirects('http://localhost/admin');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testWrongPasswordIsRejected(): void
    {
        $user = $this->createUser(['ROLE_EDITOR']);

        $this->submitLogin('/admin/login', (string) $user->getEmail(), 'mauvais-mot-de-passe');

        self::assertResponseRedirects('http://localhost/admin/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.text-admin-danger');
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testUnknownAccountIsRejectedWithTheSameResponse(): void
    {
        $this->submitLogin('/admin/login', 'inconnu@test.local', self::PASSWORD);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testAnonymizedAccountCannotLogIn(): void
    {
        $user = $this->createUser(['ROLE_EDITOR']);
        $user->markAnonymized();
        $this->em->flush();

        $this->submitLogin('/admin/login', (string) $user->getEmail(), self::PASSWORD);

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testPortalAccountCanLogInToThePortal(): void
    {
        $user = $this->createUser(['ROLE_FAMILLE']);

        $this->submitLogin('/mon-compte/connexion', (string) $user->getEmail(), self::PASSWORD);

        self::assertResponseRedirects();
        $this->client->request('GET', '/mon-compte');
        self::assertResponseIsSuccessful();
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->loginAs(['ROLE_EDITOR']);
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/logout');

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    public function testPasswordIsStoredHashed(): void
    {
        $user = $this->createUser(['ROLE_EDITOR']);

        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertTrue(password_verify(self::PASSWORD, (string) $user->getPassword()));
    }

    public function testAdminAreaSendsNoIndexHeader(): void
    {
        $this->loginAs(['ROLE_EDITOR']);

        $this->client->request('GET', '/admin');

        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
    }

    public function testResetPasswordDoesNotRevealWhetherAnAccountExists(): void
    {
        $known = $this->createUser(['ROLE_FAMILLE']);

        $responses = [];
        foreach ([(string) $known->getEmail(), 'personne@test.local'] as $email) {
            $this->client->restart();
            $crawler = $this->client->request('GET', '/reset-password');
            $form = $crawler->filter('form')->first()->form();
            $form->setValues(['reset_password_request_form[email]' => $email]);
            $this->client->submit($form);
            $responses[] = $this->client->getResponse()->getStatusCode().' '.$this->client->getResponse()->headers->get('Location');
        }

        self::assertSame($responses[0], $responses[1], 'La réponse ne doit pas permettre de deviner si un e-mail a un compte.');
    }
}

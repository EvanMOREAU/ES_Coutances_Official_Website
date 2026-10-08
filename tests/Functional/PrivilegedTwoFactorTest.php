<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;

/** Les comptes développeur et administrateur doivent activer une double authentification pour utiliser le back-office. */
final class PrivilegedTwoFactorTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        $_SERVER['ENFORCE_PRIVILEGED_2FA'] = $_ENV['ENFORCE_PRIVILEGED_2FA'] = '1';
        putenv('ENFORCE_PRIVILEGED_2FA=1');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_SERVER['ENFORCE_PRIVILEGED_2FA'] = $_ENV['ENFORCE_PRIVILEGED_2FA'] = '0';
        putenv('ENFORCE_PRIVILEGED_2FA=0');
        parent::tearDown();
    }

    public function testAdminWithoutSecondFactorIsSentToSecuritySettings(): void
    {
        $this->loginAs(['ROLE_ADMIN']);

        $this->client->request('GET', '/admin/utilisateurs');

        self::assertResponseRedirects('/admin/parametres/securite');
    }

    public function testSecuritySettingsStayReachableToSetItUp(): void
    {
        $this->loginAs(['ROLE_DEV']);

        $this->client->request('GET', '/admin/parametres/securite');

        self::assertResponseIsSuccessful();
    }

    public function testAdminWithEmailCodeEnabledIsNotBlocked(): void
    {
        $user = $this->loginAs(['ROLE_ADMIN']);
        $user->setEmailAuthEnabled(true);
        $this->em->flush();

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
    }

    public function testEditorsAreNotAffected(): void
    {
        $this->loginAs(['ROLE_EDITOR']);

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
    }
}

<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEveryUserHasRoleUser(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame(['ROLE_EDITOR', 'ROLE_USER'], $user->setRoles(['ROLE_EDITOR'])->getRoles());
        self::assertCount(1, array_keys($user->setRoles(['ROLE_USER'])->getRoles(), 'ROLE_USER', true));
    }

    public function testEmptyPasswordDoesNotOverwriteExistingOne(): void
    {
        $user = (new User())->setPassword('hash');

        $user->setPassword(null)->setPassword('');

        self::assertSame('hash', $user->getPassword());
        self::assertSame('autre', $user->setPassword('autre')->getPassword());
    }

    public function testDisplayName(): void
    {
        $user = (new User())->setNom('Dupont');
        self::assertSame('Dupont', $user->getNomComplet());
        self::assertSame('D', $user->getInitiale());

        $user->setPrenom('marie');
        self::assertSame('marie Dupont', $user->getNomComplet());
        self::assertSame('M', $user->getInitiale());

        $user->setPrenom('');
        self::assertNull($user->getPrenom());
    }

    public function testStaffDetection(): void
    {
        self::assertFalse((new User())->isStaff());
        self::assertFalse((new User())->setRoles(['ROLE_FAMILLE'])->isStaff());
        self::assertTrue((new User())->setRoles(['ROLE_EDITOR'])->isStaff());
        self::assertTrue((new User())->setRoles(['ROLE_ADMIN'])->isStaff());
        self::assertTrue((new User())->setRoles(['ROLE_DEV'])->isStaff());
    }

    public function testTwoFactorFlags(): void
    {
        $user = new User();
        self::assertFalse($user->hasTwoFactorEnabled());

        $user->setEmailAuthEnabled(true);
        self::assertTrue($user->hasTwoFactorEnabled());

        $user->setEmailAuthEnabled(false)->setTotpSecret('SECRET');
        self::assertTrue($user->isTotpAuthenticationEnabled());
        self::assertTrue($user->hasTwoFactorEnabled());
    }

    public function testBackupCodesAreVerifiedAndInvalidated(): void
    {
        $user = (new User())->setBackupCodes([password_hash('code-1', PASSWORD_BCRYPT), password_hash('code-2', PASSWORD_BCRYPT)]);

        self::assertTrue($user->isBackupCode('code-1'));
        self::assertFalse($user->isBackupCode('inconnu'));

        $user->invalidateBackupCode('code-1');

        self::assertFalse($user->isBackupCode('code-1'));
        self::assertTrue($user->isBackupCode('code-2'));
        self::assertCount(1, $user->getBackupCodes());
    }

    public function testAnonymization(): void
    {
        $user = new User();
        self::assertFalse($user->isAnonymized());

        $user->markAnonymized();

        self::assertTrue($user->isAnonymized());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getAnonymizedAt());
    }
}

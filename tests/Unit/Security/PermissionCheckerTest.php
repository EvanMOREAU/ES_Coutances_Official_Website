<?php

namespace App\Tests\Unit\Security;

use App\Entity\ProfilAutorisation;
use App\Entity\User;
use App\Security\PermissionCatalog;
use App\Security\PermissionChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class PermissionCheckerTest extends TestCase
{
    private function checker(?User $current = null): PermissionChecker
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($current);

        return new PermissionChecker($security);
    }

    /** @param list<string> $roles */
    private function user(array $roles, bool $restricted = false): User
    {
        $user = (new User())->setEmail('a@b.fr')->setRoles($roles)->setAccesRestreint($restricted);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, random_int(1, 1_000_000));

        return $user;
    }

    public function testAnonymousHasNothing(): void
    {
        self::assertFalse($this->checker(null)->can('famille.voir'));
    }

    public function testDeveloperHasEverything(): void
    {
        $dev = $this->user(['ROLE_DEV']);
        $checker = $this->checker($dev);

        self::assertTrue($checker->can('famille.voir'));
        self::assertTrue($checker->hasFullAccess($dev));
        self::assertSame(PermissionCatalog::all(), $checker->grantedCodes($dev));
    }

    public function testUnrestrictedAdminHasEverythingButRestrictedAdminDoesNot(): void
    {
        $free = $this->user(['ROLE_ADMIN']);
        $restricted = $this->user(['ROLE_ADMIN'], true);

        self::assertTrue($this->checker($free)->can('utilisateur.supprimer'));
        self::assertFalse($this->checker($restricted)->can('utilisateur.supprimer'));
    }

    public function testProfileAddedAndRemovedPermissions(): void
    {
        $profil = (new ProfilAutorisation())->setNom('Test')->setPermissions(['famille.voir', 'licencie.voir']);
        $user = $this->user(['ROLE_EDITOR'])
            ->setProfil($profil)
            ->setPermissionsAjoutees(['equipe.voir'])
            ->setPermissionsRetirees(['licencie.voir']);
        $checker = $this->checker($user);

        self::assertTrue($checker->can('famille.voir'));
        self::assertTrue($checker->can('equipe.voir'));
        self::assertFalse($checker->can('licencie.voir'), 'Une autorisation retirée l\'emporte sur le profil.');
        self::assertFalse($checker->can('saison.voir'));
        self::assertFalse($checker->hasFullAccess($user));
    }

    public function testAnyOfSeveralCodesIsEnough(): void
    {
        $user = $this->user(['ROLE_EDITOR'])->setPermissionsAjoutees(['messagerie.support']);

        self::assertTrue($this->checker($user)->can('messagerie.utiliser, messagerie.support'));
        self::assertFalse($this->checker($user)->can('messagerie.utiliser'));
    }

    public function testUnknownCodesInStoredDataAreIgnored(): void
    {
        $user = $this->user(['ROLE_EDITOR'])->setPermissionsAjoutees(['ancien.code']);

        self::assertSame([], $this->checker($user)->grantedCodes($user));
    }

    public function testExplicitUserArgumentOverridesCurrentUser(): void
    {
        $dev = $this->user(['ROLE_DEV']);

        self::assertTrue($this->checker(null)->can('famille.voir', $dev));
    }
}

<?php

namespace App\Tests\Unit\Security;

use App\Security\PermissionCatalog;
use PHPUnit\Framework\TestCase;

final class PermissionCatalogTest extends TestCase
{
    public function testCodesAreUniqueAndWellFormed(): void
    {
        $all = PermissionCatalog::all();

        self::assertNotEmpty($all);
        self::assertSame($all, array_values(array_unique($all)), 'Chaque code doit être unique.');
        foreach ($all as $code) {
            self::assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+$/', $code);
        }
    }

    public function testExists(): void
    {
        self::assertTrue(PermissionCatalog::exists('famille.voir'));
        self::assertFalse(PermissionCatalog::exists('famille.inconnu'));
        self::assertFalse(PermissionCatalog::exists(''));
    }

    public function testSanitizeDropsUnknownAndDuplicateCodes(): void
    {
        $clean = PermissionCatalog::sanitize(['famille.voir', 'ancien.code', 'famille.voir', 'licencie.creer']);

        self::assertSame(['famille.voir', 'licencie.creer'], $clean);
    }

    public function testSanitizeAcceptsTraversable(): void
    {
        self::assertSame(['equipe.voir'], PermissionCatalog::sanitize(new \ArrayIterator(['equipe.voir', 'x.y'])));
    }

    public function testLabel(): void
    {
        self::assertSame('Familles — Voir', PermissionCatalog::label('famille.voir'));
        self::assertSame('inconnu.truc', PermissionCatalog::label('inconnu.truc'));
    }
}

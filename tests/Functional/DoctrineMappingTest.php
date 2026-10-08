<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use Doctrine\ORM\Tools\SchemaValidator;

/**
 * Garde-fou : une entité modifiée sans migration (ou une migration qui s'écarte du mapping)
 * fait échouer ce test avant d'arriver en production.
 */
final class DoctrineMappingTest extends DatabaseTestCase
{
    public function testMappingIsValid(): void
    {
        $errors = (new SchemaValidator($this->em))->validateMapping();

        self::assertSame([], $errors, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function testDatabaseSchemaMatchesTheEntitiesThroughMigrations(): void
    {
        // La table de suivi des migrations n'est pas une entité : elle n'est pas une différence.
        $differences = array_values(array_filter(
            (new SchemaValidator($this->em))->getUpdateSchemaList(),
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
        ));

        self::assertSame([], $differences, "Il manque une migration :\n".implode("\n", $differences));
    }
}

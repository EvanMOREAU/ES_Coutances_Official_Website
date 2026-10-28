<?php

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;

/** Préfixe toutes les tables du schéma (entités et tables de jointure) par « es_ », quel que soit le nom déclaré. */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final class TablePrefixListener
{
    public const PREFIX = 'es_';

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata = $args->getClassMetadata();

        // Les sous-classes en héritage « table unique » partagent la table de leur parent, déjà préfixée.
        if (!$metadata->isInheritanceTypeNone() && !$metadata->isRootEntity()) {
            return;
        }

        if (!$metadata->isMappedSuperclass) {
            $metadata->setPrimaryTable(['name' => self::prefixed($metadata->getTableName())] + $metadata->table);
        }

        foreach ($metadata->associationMappings as $mapping) {
            if ($mapping instanceof ManyToManyOwningSideMapping) {
                $mapping->joinTable->name = self::prefixed($mapping->joinTable->name);
            }
        }
    }

    public static function prefixed(string $table): string
    {
        $table = trim($table, '`"');

        return str_starts_with($table, self::PREFIX) ? $table : self::PREFIX.$table;
    }
}

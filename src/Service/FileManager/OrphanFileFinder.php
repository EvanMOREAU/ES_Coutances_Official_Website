<?php

namespace App\Service\FileManager;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Repère les « fichiers morts » du dossier d'envoi du site (public/uploads) :
 * ceux dont le nom n'apparaît nulle part en base de données (champ d'une fiche,
 * image glissée dans un texte riche…). Seul ce dossier est analysé : les images
 * statiques du thème et les documents privés ne sont jamais proposés au nettoyage.
 */
class OrphanFileFinder
{
    /** Tables qui citent des chemins sans « utiliser » le fichier (favoris du gestionnaire, journal). */
    private const IGNORED_TABLES = ['file_favorite', 'audit_log'];

    public function __construct(
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<array<string, mixed>> entrées du gestionnaire, les plus lourdes d'abord */
    public function find(): array
    {
        $root = $this->storage->resolve('fichiers-du-site');
        $files = $this->storage->filesUnder($root, ignoreFilter: true);
        if ($files === []) {
            return [];
        }

        $haystack = $this->databaseText();
        $orphans = array_values(array_filter($files, static function (array $file) use ($haystack): bool {
            $name = $file['name'];

            return !str_contains($haystack, $name) && !str_contains($haystack, rawurlencode($name));
        }));

        usort($orphans, static fn (array $a, array $b) => ($b['size'] ?? 0) <=> ($a['size'] ?? 0));

        return $orphans;
    }

    /** Tout le texte stocké en base (colonnes texte / json), concaténé pour une recherche simple. */
    private function databaseText(): string
    {
        $connection = $this->em->getConnection();
        $chunks = [];

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            $table = $metadata->getTableName();
            if (in_array($table, self::IGNORED_TABLES, true) || $metadata->isEmbeddedClass || $metadata->isMappedSuperclass) {
                continue;
            }

            $columns = [];
            foreach ($metadata->fieldMappings as $mapping) {
                $type = \is_array($mapping) ? $mapping['type'] : $mapping->type;
                if (in_array($type, ['string', 'text', 'json', 'simple_array', 'array'], true)) {
                    $columns[] = $connection->quoteSingleIdentifier(\is_array($mapping) ? $mapping['columnName'] : $mapping->columnName);
                }
            }
            if ($columns === []) {
                continue;
            }

            $sql = sprintf('SELECT %s FROM %s', implode(', ', $columns), $connection->quoteSingleIdentifier($table));
            foreach ($connection->iterateNumeric($sql) as $row) {
                foreach ($row as $value) {
                    if (\is_string($value) && $value !== '') {
                        $chunks[] = $value;
                    }
                }
            }
        }

        return implode("\n", $chunks);
    }
}

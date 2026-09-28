<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Lit le journal des modifications dans changelog/releases.yaml. Le fichier est
 * versionné avec le code : il se met à jour au déploiement, sans base de données.
 */
class ChangelogFile
{
    public const TYPES = [
        'added'    => 'Ajouté',
        'improved' => 'Amélioré',
        'fixed'    => 'Corrigé',
        'removed'  => 'Retiré',
    ];

    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * @return list<array{version: string, label: string, date: \DateTimeImmutable, titre: ?string, groups: array<string, list<string>>, count: int}>
     */
    public function releases(): array
    {
        $file = $this->projectDir.'/changelog/releases.yaml';
        if (!is_file($file)) {
            return [];
        }

        $releases = [];
        foreach ((array) Yaml::parseFile($file) as $row) {
            if (!is_array($row) || empty($row['version'])) {
                continue;
            }
            $groups = [];
            foreach (array_keys(self::TYPES) as $type) {
                $lines = array_values(array_filter(array_map('strval', (array) ($row[$type] ?? []))));
                if ([] !== $lines) {
                    $groups[$type] = $lines;
                }
            }
            $releases[] = [
                'version' => (string) $row['version'],
                'label'   => 'v'.$row['version'],
                'date'    => new \DateTimeImmutable((string) ($row['date'] ?? 'today')),
                'titre'   => isset($row['titre']) ? (string) $row['titre'] : null,
                'groups'  => $groups,
                'count'   => array_sum(array_map('count', $groups)),
            ];
        }

        // Plus récentes d'abord, quel que soit l'ordre du fichier.
        usort($releases, static fn (array $a, array $b) => $b['date'] <=> $a['date']);

        return $releases;
    }
}

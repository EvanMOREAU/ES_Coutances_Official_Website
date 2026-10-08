<?php

namespace App\Service\Backup;

use Doctrine\DBAL\Connection;

/**
 * Sauvegarde et restauration de la base de données (MySQL / MariaDB), sans dépendre de `mysqldump`.
 *
 * Le fichier produit est un script SQL compressé (.sql.gz) à raison d'une instruction par ligne
 * (retours à la ligne des valeurs échappés), ce qui permet de le rejouer ligne à ligne sans
 * analyseur SQL. Il se termine par un marqueur : un fichier incomplet est refusé à la restauration.
 *
 * Les sauvegardes sont rangées dans var/backups/db (hors du dossier public). Elles ne sont JAMAIS
 * supprimées automatiquement, à l'exception des sauvegardes « automatiques » les plus anciennes
 * (voir pruneAutomatic) : celles prises avant une mise à jour ou à la demande sont conservées.
 */
class DatabaseBackup
{
    public const KIND_UPDATE = 'maj';
    public const KIND_AUTO   = 'auto';
    public const KIND_MANUAL = 'manuel';

    public const KINDS = [
        self::KIND_UPDATE => 'Avant une mise à jour',
        self::KIND_AUTO   => 'Automatique',
        self::KIND_MANUAL => 'Manuelle',
    ];

    private const HEADER = '-- escoutances-backup v1';
    private const FOOTER = '-- fin de la sauvegarde';
    private const FILE_PATTERN = '/^(maj|auto|manuel)-\d{8}-\d{6}(-[a-z0-9]{1,12})?\.sql\.gz$/';

    /** Taille approximative (octets) d'une instruction INSERT groupée. */
    private const INSERT_BATCH_BYTES = 400_000;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
    }

    public function directory(): string
    {
        $dir = $this->projectDir.'/var/backups/db';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier des sauvegardes (%s).', $dir));
        }

        return $dir;
    }

    /**
     * @param string $suffix complément facultatif du nom (ex. empreinte du commit) : minuscules et chiffres
     *
     * @return array{file: string, path: string, size: int}
     */
    public function create(string $kind, string $suffix = ''): array
    {
        if (!isset(self::KINDS[$kind])) {
            throw new \InvalidArgumentException('Type de sauvegarde inconnu.');
        }
        $suffix = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $suffix));
        $file   = sprintf('%s-%s%s.sql.gz', $kind, date('Ymd-His'), '' !== $suffix ? '-'.substr($suffix, 0, 12) : '');
        $dir    = $this->directory();
        $final  = $dir.'/'.$file;
        $temp   = $final.'.part';
        if (is_file($final)) {
            $file  = sprintf('%s-%s-%s.sql.gz', $kind, date('Ymd-His'), bin2hex(random_bytes(2)));
            $final = $dir.'/'.$file;
            $temp  = $final.'.part';
        }

        $gz = @gzopen($temp, 'wb6');
        if (false === $gz) {
            throw new \RuntimeException('Impossible d\'écrire la sauvegarde de la base de données (espace disque ou droits ?).');
        }
        @chmod($temp, 0600);

        try {
            $this->dump($gz);
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($temp);

            throw $e;
        }
        gzclose($gz);

        if (!@rename($temp, $final)) {
            @unlink($temp);

            throw new \RuntimeException('Impossible de finaliser la sauvegarde de la base de données.');
        }

        return ['file' => $file, 'path' => $final, 'size' => (int) filesize($final)];
    }

    /**
     * @return list<array{file: string, kind: string, kindLabel: string, createdAt: \DateTimeImmutable, size: int}>
     */
    public function all(): array
    {
        $dir = $this->projectDir.'/var/backups/db';
        if (!is_dir($dir)) {
            return [];
        }

        $backups = [];
        foreach (new \DirectoryIterator($dir) as $item) {
            $name = $item->getFilename();
            if (!$item->isFile() || 1 !== preg_match(self::FILE_PATTERN, $name, $m)) {
                continue;
            }
            $backups[] = [
                'file'      => $name,
                'kind'      => $m[1],
                'kindLabel' => self::KINDS[$m[1]],
                'createdAt' => (new \DateTimeImmutable('@'.$item->getMTime()))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
                'size'      => $item->getSize(),
            ];
        }
        usort($backups, static fn (array $a, array $b) => [$b['createdAt'], $b['file']] <=> [$a['createdAt'], $a['file']]);

        return $backups;
    }

    /** Chemin d'une sauvegarde d'après son nom (null si le nom est invalide ou le fichier absent). */
    public function path(string $file): ?string
    {
        if (1 !== preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }
        $path = $this->projectDir.'/var/backups/db/'.$file;

        return is_file($path) ? $path : null;
    }

    /**
     * Supprime les sauvegardes automatiques les plus anciennes, en gardant les $keep plus récentes.
     * Les sauvegardes prises avant une mise à jour ou à la demande ne sont jamais touchées.
     *
     * @return int nombre de fichiers supprimés
     */
    public function pruneAutomatic(int $keep): int
    {
        $auto = array_values(array_filter($this->all(), static fn (array $b) => self::KIND_AUTO === $b['kind']));
        $removed = 0;
        foreach (array_slice($auto, max(0, $keep)) as $old) {
            if (@unlink($this->projectDir.'/var/backups/db/'.$old['file'])) {
                ++$removed;
            }
        }

        return $removed;
    }

    // -- Écriture du script SQL ------------------------------------------------

    /** @param resource $gz */
    private function dump($gz): void
    {
        $write = static function (string $line) use ($gz): void {
            if (false === gzwrite($gz, $line."\n")) {
                throw new \RuntimeException('Écriture de la sauvegarde interrompue (disque plein ?).');
            }
        };

        $write(self::HEADER);
        $write('-- Base : '.$this->connection->fetchOne('SELECT DATABASE()'));
        $write('-- Date : '.date('c'));
        $write('SET NAMES utf8mb4;');
        $write('SET FOREIGN_KEY_CHECKS=0;');
        $write("SET sql_mode='NO_AUTO_VALUE_ON_ZERO';");

        $tables = [];
        $views  = [];
        foreach ($this->connection->fetchAllNumeric('SHOW FULL TABLES') as [$name, $type]) {
            if ('VIEW' === $type) {
                $views[] = (string) $name;
            } else {
                $tables[] = (string) $name;
            }
        }

        foreach ($tables as $table) {
            $quoted = $this->connection->quoteSingleIdentifier($table);
            $create = (string) $this->connection->fetchNumeric('SHOW CREATE TABLE '.$quoted)[1];
            $write('DROP TABLE IF EXISTS '.$quoted.';');
            $write($this->oneLine($create).';');
            $this->dumpRows($table, $quoted, $write);
        }
        foreach ($views as $view) {
            $quoted = $this->connection->quoteSingleIdentifier($view);
            $create = (string) $this->connection->fetchNumeric('SHOW CREATE VIEW '.$quoted)[1];
            $write('DROP VIEW IF EXISTS '.$quoted.';');
            $write($this->oneLine((string) preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', $create)).';');
        }

        $write('SET FOREIGN_KEY_CHECKS=1;');
        $write(self::FOOTER);
    }

    private function oneLine(string $sql): string
    {
        return trim((string) preg_replace('/\s*\R\s*/', ' ', $sql));
    }

    /** @param \Closure(string): void $write */
    private function dumpRows(string $table, string $quotedTable, \Closure $write): void
    {
        $result = $this->connection->executeQuery('SELECT * FROM '.$quotedTable);
        $prefix = '';
        $values = [];
        $bytes  = 0;

        while (false !== ($row = $result->fetchAssociative())) {
            if ('' === $prefix) {
                $columns = implode(',', array_map($this->connection->quoteSingleIdentifier(...), array_keys($row)));
                $prefix  = 'INSERT INTO '.$quotedTable.' ('.$columns.') VALUES ';
            }
            $tuple    = '('.implode(',', array_map($this->literal(...), $row)).')';
            $values[] = $tuple;
            $bytes   += strlen($tuple);
            if ($bytes >= self::INSERT_BATCH_BYTES) {
                $write($prefix.implode(',', $values).';');
                $values = [];
                $bytes  = 0;
            }
        }
        if ([] !== $values) {
            $write($prefix.implode(',', $values).';');
        }
    }

    private function literal(mixed $value): string
    {
        if (null === $value) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.17F', $value), '0'), '.') ?: '0';
        }
        if (is_resource($value)) {
            $value = (string) stream_get_contents($value);
        }
        $value = (string) $value;
        if ('' === $value) {
            return "''";
        }
        // Contenu binaire (pas de l'UTF-8 valide) : écrit en hexadécimal pour ne rien déformer.
        if (!mb_check_encoding($value, 'UTF-8')) {
            return '0x'.bin2hex($value);
        }

        return $this->connection->quote($value);
    }

    // -- Restauration ----------------------------------------------------------

    /**
     * Remplace le contenu de la base par celui de la sauvegarde.
     *
     * Toutes les tables actuelles sont supprimées (y compris celles qu'une migration a ajoutées depuis),
     * puis le script est rejoué. Le fichier est vérifié en entier AVANT de toucher à la base.
     *
     * @throws \RuntimeException si le fichier est illisible ou incomplet, ou si une instruction échoue
     */
    public function restore(string $path): void
    {
        $this->assertComplete($path);

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->connection->fetchAllNumeric('SHOW FULL TABLES') as [$name, $type]) {
            $quoted = $this->connection->quoteSingleIdentifier((string) $name);
            $this->connection->executeStatement(('VIEW' === $type ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ').$quoted);
        }

        $gz = gzopen($path, 'rb');
        if (false === $gz) {
            throw new \RuntimeException('Sauvegarde illisible.');
        }
        try {
            while (false !== ($line = gzgets($gz))) {
                $statement = trim($line);
                if ('' === $statement || str_starts_with($statement, '--')) {
                    continue;
                }
                $this->connection->executeStatement(str_ends_with($statement, ';') ? substr($statement, 0, -1) : $statement);
            }
        } finally {
            gzclose($gz);
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** @throws \RuntimeException */
    private function assertComplete(string $path): void
    {
        $gz = is_file($path) ? @gzopen($path, 'rb') : false;
        if (false === $gz) {
            throw new \RuntimeException('Sauvegarde de la base introuvable ou illisible : '.basename($path));
        }

        $first = null;
        $last  = null;
        while (false !== ($line = gzgets($gz))) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $first ??= $line;
            $last = $line;
        }
        $ended = gzeof($gz);
        gzclose($gz);

        if (self::HEADER !== $first || self::FOOTER !== $last || !$ended) {
            throw new \RuntimeException('Sauvegarde de la base incomplète ou invalide : '.basename($path));
        }
    }
}

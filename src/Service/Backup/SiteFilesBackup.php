<?php

namespace App\Service\Backup;

/**
 * Archive (.zip) des fichiers du site — code, dépendances (vendor), configuration versionnée — prise
 * avant une mise à jour pour pouvoir y revenir si elle échoue.
 *
 * Ne sont PAS archivés : le dépôt Git (.git), les données et caches (var/), les fichiers envoyés
 * (public/uploads), les images du site (public/images), les assets compilés (public/assets, régénérés),
 * node_modules et les fichiers .env.local* (secrets : ils ne doivent pas être dupliqués).
 *
 * Les archives sont rangées dans var/backups/site (hors du dossier public) et peuvent être supprimées
 * une fois la mise à jour validée.
 */
class SiteFilesBackup
{
    private const FILE_PATTERN = '/^site-\d{8}-\d{6}(-[a-z0-9]{1,12})?\.zip$/';

    /** Dossiers exclus de l'archive (chemins relatifs à la racine du projet). */
    private const EXCLUDED = ['.git', 'var', 'node_modules', 'public/uploads', 'public/images', 'public/assets'];

    /** Dossiers dans lesquels les fichiers absents de l'archive sont supprimés à la restauration (créés par la mise à jour). */
    private const PRUNED = ['vendor', 'public/bundles'];

    /** ZipArchive garde chaque fichier ouvert jusqu'à la fermeture : on referme l'archive par lots. */
    private const REOPEN_EVERY = 300;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function directory(): string
    {
        $dir = $this->projectDir.'/var/backups/site';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier des sauvegardes (%s).', $dir));
        }

        return $dir;
    }

    /** @return array{file: string, path: string, size: int, files: int} */
    public function create(string $suffix = ''): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("L'extension PHP « zip » est nécessaire pour sauvegarder les fichiers du site.");
        }

        $suffix = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $suffix));
        $dir    = $this->directory();
        $file   = sprintf('site-%s%s.zip', date('Ymd-His'), '' !== $suffix ? '-'.substr($suffix, 0, 12) : '');
        $final  = $dir.'/'.$file;
        $temp   = $final.'.part';

        [$entries, $total] = $this->scan();
        $free = @disk_free_space($dir);
        if (false !== $free && $free < $total * 0.5) {
            throw new \RuntimeException(sprintf('Espace disque insuffisant pour sauvegarder les fichiers du site (%d Mo libres, %d Mo à archiver).', $free / 1048576, $total / 1048576));
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($temp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException("Impossible de créer l'archive des fichiers du site.");
        }

        try {
            $count = 0;
            foreach ($entries as [$absolute, $relative]) {
                if (!$zip->addFile($absolute, $relative)) {
                    throw new \RuntimeException(sprintf('Impossible d\'archiver « %s ».', $relative));
                }
                if ('Windows' !== PHP_OS_FAMILY) {
                    $zip->setExternalAttributesName($relative, \ZipArchive::OPSYS_UNIX, (fileperms($absolute) & 0xFFFF) << 16);
                }
                if (0 === ++$count % self::REOPEN_EVERY) {
                    $this->reopen($zip, $temp);
                }
            }
            if (true !== $zip->close()) {
                throw new \RuntimeException("L'archive des fichiers du site n'a pas pu être écrite (disque plein ?).");
            }
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($temp);

            throw $e;
        }

        if (!@rename($temp, $final)) {
            @unlink($temp);

            throw new \RuntimeException("Impossible de finaliser l'archive des fichiers du site.");
        }
        @chmod($final, 0600);

        return ['file' => $file, 'path' => $final, 'size' => (int) filesize($final), 'files' => count($entries)];
    }

    private function reopen(\ZipArchive $zip, string $path): void
    {
        if (true !== $zip->close() || true !== $zip->open($path)) {
            throw new \RuntimeException("L'archive des fichiers du site n'a pas pu être écrite (disque plein ?).");
        }
    }

    /** Chemin d'une archive d'après son nom (null si le nom est invalide ou le fichier absent). */
    public function path(string $file): ?string
    {
        if (1 !== preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }
        $path = $this->projectDir.'/var/backups/site/'.$file;

        return is_file($path) ? $path : null;
    }

    /** Supprime une archive. Retourne false si elle n'existe pas (déjà supprimée). */
    public function delete(string $file): bool
    {
        $path = $this->path($file);

        return null !== $path && @unlink($path);
    }

    /**
     * Remet les fichiers du site dans l'état de l'archive : les fichiers archivés sont réécrits et, dans
     * vendor/ et public/bundles/, ceux que la mise à jour a ajoutés sont supprimés.
     * (Le code suivi par Git est ramené à l'ancien commit à part, par DeployService.)
     *
     * @return int nombre de fichiers restaurés
     */
    public function restore(string $file): int
    {
        $path = $this->path($file) ?? throw new \RuntimeException("Archive des fichiers du site introuvable : $file");

        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::CHECKCONS)) {
            throw new \RuntimeException("Archive des fichiers du site illisible ou corrompue : $file");
        }

        try {
            $names = [];
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = (string) $zip->getNameIndex($i);
                if ('' === $name || str_ends_with($name, '/') || str_contains($name, '..')) {
                    continue;
                }
                $names[$name] = $i;
            }
            if ([] === $names) {
                throw new \RuntimeException("Archive des fichiers du site vide : $file");
            }

            if (!$zip->extractTo($this->projectDir)) {
                throw new \RuntimeException("Extraction de l'archive des fichiers du site impossible : $file");
            }
            if ('Windows' !== PHP_OS_FAMILY) {
                foreach ($names as $name => $index) {
                    if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) && \ZipArchive::OPSYS_UNIX === $opsys && ($attributes >> 16) > 0) {
                        @chmod($this->projectDir.'/'.$name, ($attributes >> 16) & 0777);
                    }
                }
            }
        } finally {
            $zip->close();
        }

        foreach (self::PRUNED as $relativeDir) {
            $this->prune($relativeDir, $names);
        }

        return count($names);
    }

    /** @param array<string, int> $keep fichiers de l'archive (clés = chemins relatifs) */
    private function prune(string $relativeDir, array $keep): void
    {
        $root = $this->projectDir.'/'.$relativeDir;
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = $relativeDir.'/'.str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname()); // ne réussit que si le dossier est vide
            } elseif (!isset($keep[$relative])) {
                @unlink($item->getPathname());
            }
        }
    }

    /**
     * @return array{0: list<array{0: string, 1: string}>, 1: int} [[chemin absolu, chemin relatif], octets au total]
     */
    private function scan(): array
    {
        $entries = [];
        $total   = 0;
        $root    = rtrim(str_replace('\\', '/', $this->projectDir), '/');

        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function (\SplFileInfo $file) use ($root): bool {
                $relative = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/');
                if (in_array($relative, self::EXCLUDED, true) || $file->isLink() && $file->isDir()) {
                    return false;
                }

                $name = $file->getFilename();

                return !(str_starts_with($name, '.env.') && str_contains($name, 'local'));
            },
        ));

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $entries[] = [$file->getPathname(), ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/')];
            $total    += $file->getSize();
        }

        return [$entries, $total];
    }
}

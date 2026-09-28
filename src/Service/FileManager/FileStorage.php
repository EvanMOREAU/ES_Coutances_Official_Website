<?php

namespace App\Service\FileManager;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Accès au disque du gestionnaire de fichiers.
 *
 * Le gestionnaire n'expose PAS tout le projet : uniquement quelques « racines »
 * déclarées ci-dessous (Documents, Images, Fichiers du site). Tout le reste
 * (css, js, code, configuration…) n'existe tout simplement pas de son point de
 * vue. Les chemins manipulés sont des chemins virtuels (« documents/Joueurs/x.pdf »)
 * traduits en chemins réels après vérifications strictes (pas de « .. », pas de
 * lien symbolique qui sortirait de la racine, pas de fichier caché).
 *
 * Dossiers « déclarés » : les racines, leurs montages et les dossiers d'envoi
 * du site (slides, partenaires…) ne peuvent être ni supprimés ni renommés ; on
 * peut en revanche gérer leur contenu.
 */
class FileStorage
{
    /** Extensions acceptées à l'envoi. */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp',
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp',
        'txt', 'md', 'zip', 'mp4', 'webm', 'mp3', 'wav', 'ogg',
    ];

    public const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;

    /** Dossiers d'envoi utilisés par l'application (voir vich_uploader.yaml) : jamais supprimables. */
    private const DECLARED_UPLOAD_DIRS = ['articles', 'avatars', 'banniere', 'membres', 'offres', 'pages', 'partenaires', 'photos', 'rejoindre', 'slides'];

    private const KINDS = [
        'image'   => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp'],
        'pdf'     => ['pdf'],
        'doc'     => ['doc', 'docx', 'odt', 'rtf'],
        'sheet'   => ['xls', 'xlsx', 'ods', 'csv'],
        'slide'   => ['ppt', 'pptx', 'odp'],
        'text'    => ['txt', 'md', 'log', 'json', 'xml'],
        'archive' => ['zip'],
        'video'   => ['mp4', 'webm'],
        'audio'   => ['mp3', 'wav', 'ogg'],
    ];

    /** @var array<string, array{label: string, icon: string, real: string, readOnly: bool, private: bool, mounts: list<array{name: string, label: string, real: string}>}> */
    private array $roots;

    public function __construct(string $projectDir)
    {
        $root = rtrim(str_replace('\\', '/', $projectDir), '/');

        $this->roots = [
            'documents' => [
                'label'    => 'Documents',
                'icon'     => 'folder-closed',
                'real'     => $root.'/var/documents',
                'readOnly' => false,
                'private'  => true,
                'mounts'   => [],
            ],
            'images' => [
                'label'    => 'Images',
                'icon'     => 'images',
                'real'     => $root.'/public/images',
                'readOnly' => false,
                'private'  => false,
                'mounts'   => [],
            ],
            'fichiers-du-site' => [
                'label'    => 'Fichiers du site',
                'icon'     => 'cloud',
                'real'     => $root.'/public/uploads',
                'readOnly' => false,
                'private'  => false,
                'mounts'   => [],
            ],
        ];

        // Le dossier des documents privés est créé à la demande.
        $documents = $this->roots['documents']['real'];
        if (!is_dir($documents)) {
            @mkdir($documents, 0775, true);
        }
    }

    // -- Résolution des chemins ------------------------------------------------

    /**
     * @throws FileManagerException si le chemin est invalide, hors des racines ou inexistant
     */
    public function resolve(string $virtual): ResolvedPath
    {
        $segments = $this->segments($virtual);
        if ($segments === []) {
            return new ResolvedPath('', null, null, true, true, true, false);
        }

        $rootKey = array_shift($segments);
        $root    = $this->roots[$rootKey] ?? throw new FileManagerException('Dossier introuvable.');

        $base     = $root['real'];
        $mountKey = null;
        $readOnly = $root['readOnly'];
        if ($segments !== []) {
            foreach ($root['mounts'] as $mount) {
                if ($mount['name'] === $segments[0]) {
                    $base     = $mount['real'];
                    $mountKey = $mount['name'];
                    $readOnly = true;
                    array_shift($segments);
                    break;
                }
            }
        }

        $baseReal = realpath($base);
        if ($baseReal === false) {
            if ($mountKey !== null && $segments === []) {
                // Un montage dont le dossier n'existe pas encore (aucun document envoyé) : liste vide.
                return new ResolvedPath($rootKey.'/'.$mountKey, null, $rootKey, false, true, true, $root['private']);
            }
            throw new FileManagerException('Dossier introuvable.');
        }

        $real = $segments === [] ? $baseReal : $baseReal.\DIRECTORY_SEPARATOR.implode(\DIRECTORY_SEPARATOR, $segments);
        $resolved = realpath($real);
        if ($resolved !== false && !$this->within($resolved, $baseReal)) {
            throw new FileManagerException('Chemin non autorisé.');
        }

        $virtualPath = implode('/', array_filter([$rootKey, $mountKey, ...$segments]));
        $isDeclared  = $segments === [] || ($rootKey === 'fichiers-du-site' && count($segments) === 1 && in_array($segments[0], self::DECLARED_UPLOAD_DIRS, true));

        return new ResolvedPath(
            virtual: $virtualPath,
            real: $resolved !== false ? $resolved : $real,
            rootKey: $rootKey,
            isVirtualRoot: false,
            readOnly: $readOnly,
            isDeclared: $isDeclared,
            isPrivate: $root['private'],
            exists: $resolved !== false,
        );
    }

    /** @return list<string> */
    private function segments(string $virtual): array
    {
        $virtual = trim(str_replace('\\', '/', $virtual), '/');
        if ($virtual === '') {
            return [];
        }

        $segments = explode('/', $virtual);
        foreach ($segments as $segment) {
            if (!$this->isValidName($segment)) {
                throw new FileManagerException('Chemin non autorisé.');
            }
        }

        return $segments;
    }

    private function within(string $path, string $base): bool
    {
        return $path === $base || str_starts_with($path, rtrim($base, '/\\').\DIRECTORY_SEPARATOR);
    }

    /** Un nom de fichier / dossier acceptable : ni caché, ni caractères de chemin ou de contrôle. */
    public function isValidName(string $name): bool
    {
        if ($name === '' || mb_strlen($name) > 150 || $name === '.' || $name === '..' || str_starts_with($name, '.')) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\\\\\/:*?"<>|]/u', $name)) {
            return false;
        }

        return true;
    }

    public function sanitizeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\\\\\/:*?"<>|]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', " .\t");

        return mb_substr($name, 0, 150);
    }

    // -- Lecture ----------------------------------------------------------------

    /**
     * Contenu d'un dossier (dossiers d'abord, puis fichiers, par nom).
     *
     * @return list<array<string, mixed>>
     */
    public function listing(ResolvedPath $folder): array
    {
        $entries = [];

        if ($folder->isVirtualRoot) {
            foreach ($this->roots as $key => $root) {
                $entries[] = $this->entry($key, $root['label'], $root['real'], true, $key, true, $root['readOnly'], $root['icon']);
            }

            return $entries;
        }

        if ($folder->real !== null && is_dir($folder->real)) {
            foreach (new \DirectoryIterator($folder->real) as $item) {
                if ($item->isDot() || str_starts_with($item->getFilename(), '.')) {
                    continue;
                }
                $name     = $item->getFilename();
                $virtual  = $folder->virtual.'/'.$name;
                $declared = $item->isDir() && $folder->rootKey === 'fichiers-du-site' && $folder->virtual === 'fichiers-du-site' && in_array($name, self::DECLARED_UPLOAD_DIRS, true);
                $entries[] = $this->entry($virtual, $name, $item->getPathname(), $item->isDir(), $folder->rootKey, $declared, $folder->readOnly);
            }
        }

        // Montages (ex. « Licenciés » dans Documents), même si leur dossier réel n'existe pas encore.
        if ($folder->rootKey !== null && $folder->virtual === $folder->rootKey) {
            foreach ($this->roots[$folder->rootKey]['mounts'] as $mount) {
                $entries[] = $this->entry($folder->virtual.'/'.$mount['name'], $mount['label'], $mount['real'], true, $folder->rootKey, true, true, 'users');
            }
        }

        usort($entries, static function (array $a, array $b): int {
            return [$a['type'] === 'folder' ? 0 : 1, mb_strtolower($a['name'])] <=> [$b['type'] === 'folder' ? 0 : 1, mb_strtolower($b['name'])];
        });

        return $entries;
    }

    /** @return array<string, mixed> */
    private function entry(string $virtual, string $name, string $real, bool $isDir, ?string $rootKey, bool $declared, bool $readOnly, ?string $icon = null): array
    {
        $ext  = $isDir ? '' : strtolower(pathinfo($name, \PATHINFO_EXTENSION));
        $kind = $isDir ? 'folder' : $this->kindOf($ext);

        $count = null;
        if ($isDir && is_dir($real)) {
            $count = 0;
            foreach (new \DirectoryIterator($real) as $child) {
                if (!$child->isDot() && !str_starts_with($child->getFilename(), '.')) {
                    ++$count;
                }
            }
        }

        return [
            'name'        => $name,
            'path'        => $virtual,
            'type'        => $isDir ? 'folder' : 'file',
            'kind'        => $kind,
            'ext'         => $ext,
            'size'        => $isDir ? null : (is_file($real) ? filesize($real) : 0),
            'mtime'       => file_exists($real) ? filemtime($real) : null,
            'count'       => $count,
            'protected'   => $declared,
            'readOnly'    => $readOnly,
            'previewable' => !$isDir && in_array($kind, ['image', 'pdf', 'text', 'video', 'audio'], true),
            'icon'        => $icon,
            'private'     => $rootKey !== null && ($this->roots[$rootKey]['private'] ?? false),
        ];
    }

    public function kindOf(string $ext): string
    {
        foreach (self::KINDS as $kind => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $kind;
            }
        }

        return 'other';
    }

    /**
     * Fichiers modifiés le plus récemment, toutes racines confondues.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 40): array
    {
        $found = [];
        foreach ($this->roots as $key => $root) {
            $this->collectFiles($root['real'], $key, $found);
            foreach ($root['mounts'] as $mount) {
                $this->collectFiles($mount['real'], $key.'/'.$mount['name'], $found, true);
            }
        }

        usort($found, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return array_slice($found, 0, $limit);
    }

    /** @param list<array<string, mixed>> $found */
    private function collectFiles(string $dir, string $virtualBase, array &$found, bool $readOnly = false): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $rootKey = explode('/', $virtualBase)[0];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $f) => !str_starts_with($f->getFilename(), '.'),
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
        $iterator->setMaxDepth(6);

        $count = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile() || ++$count > 5000) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            $found[]  = $this->entry($virtualBase.'/'.$relative, $file->getFilename(), $file->getPathname(), false, $rootKey, false, $readOnly);
        }
    }

    /**
     * Fichiers dont le nom contient la recherche (sous un dossier donné).
     *
     * @return list<array<string, mixed>>
     */
    public function search(ResolvedPath $under, string $query, int $limit = 60): array
    {
        $needle = mb_strtolower($query);
        $all    = $under->isVirtualRoot ? $this->recent(5000) : $this->recentUnder($under);

        return array_slice(array_values(array_filter(
            $all,
            static fn (array $e) => str_contains(mb_strtolower($e['name']), $needle),
        )), 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    private function recentUnder(ResolvedPath $folder): array
    {
        $found = [];
        if ($folder->real !== null) {
            $this->collectFiles($folder->real, $folder->virtual, $found, $folder->readOnly);
        }

        return $found;
    }

    /** @return array{used: int, quota: int} octets */
    public function usage(int $quotaBytes): array
    {
        $used = 0;
        $dirs = [];
        foreach ($this->roots as $root) {
            $dirs[] = $root['real'];
            foreach ($root['mounts'] as $mount) {
                $dirs[] = $mount['real'];
            }
        }
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $used += $file->getSize();
                }
            }
        }

        return ['used' => $used, 'quota' => $quotaBytes];
    }

    // -- Écriture ---------------------------------------------------------------

    public function createFolder(ResolvedPath $parent, string $name): string
    {
        $this->assertWritable($parent);
        $name = $this->sanitizeName($name);
        if (!$this->isValidName($name)) {
            throw new FileManagerException('Nom de dossier invalide.');
        }
        $target = $parent->real.\DIRECTORY_SEPARATOR.$name;
        if (file_exists($target)) {
            throw new FileManagerException('Un élément portant ce nom existe déjà ici.');
        }
        if (!@mkdir($target, 0775)) {
            throw new FileManagerException('Impossible de créer le dossier.');
        }

        return $parent->virtual.'/'.$name;
    }

    /**
     * @return string chemin virtuel du fichier enregistré
     */
    public function upload(ResolvedPath $parent, UploadedFile $file): string
    {
        $this->assertWritable($parent);
        if (!$file->isValid()) {
            throw new FileManagerException(sprintf('« %s » n\'a pas pu être envoyé (%s).', $file->getClientOriginalName(), $file->getErrorMessage()));
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new FileManagerException(sprintf('« %s » dépasse %d Mo.', $file->getClientOriginalName(), self::MAX_UPLOAD_BYTES / 1048576));
        }

        $name = $this->sanitizeName($file->getClientOriginalName());
        $ext  = strtolower(pathinfo($name, \PATHINFO_EXTENSION));
        if (!$this->isValidName($name) || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new FileManagerException(sprintf('Le type de fichier de « %s » n\'est pas autorisé.', $file->getClientOriginalName()));
        }

        $name = $this->uniqueName($parent->real, $name);
        $file->move($parent->real, $name);

        return $parent->virtual.'/'.$name;
    }

    /** @return string nouveau chemin virtuel */
    public function rename(ResolvedPath $item, string $newName): string
    {
        $this->assertModifiable($item);
        $newName = $this->sanitizeName($newName);
        if (!$this->isValidName($newName)) {
            throw new FileManagerException('Nom invalide.');
        }

        if (is_file($item->real)) {
            $oldExt = strtolower(pathinfo($item->real, \PATHINFO_EXTENSION));
            $newExt = strtolower(pathinfo($newName, \PATHINFO_EXTENSION));
            if ($newExt !== $oldExt) {
                throw new FileManagerException("L'extension du fichier ne peut pas être modifiée (.{$oldExt}).");
            }
        }

        $target = dirname($item->real).\DIRECTORY_SEPARATOR.$newName;
        if (strcasecmp(basename($item->real), $newName) !== 0 && file_exists($target)) {
            throw new FileManagerException('Un élément portant ce nom existe déjà ici.');
        }
        if (!@rename($item->real, $target)) {
            throw new FileManagerException('Impossible de renommer.');
        }

        return dirname($item->virtual).'/'.$newName;
    }

    public function delete(ResolvedPath $item): void
    {
        $this->assertModifiable($item);

        if (is_dir($item->real)) {
            $this->removeDirectory($item->real);

            return;
        }
        if (!@unlink($item->real)) {
            throw new FileManagerException('Impossible de supprimer ce fichier.');
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (new \DirectoryIterator($dir) as $child) {
            if ($child->isDot()) {
                continue;
            }
            if ($child->isDir() && !$child->isLink()) {
                $this->removeDirectory($child->getPathname());
            } else {
                @unlink($child->getPathname());
            }
        }
        if (!@rmdir($dir)) {
            throw new FileManagerException('Impossible de supprimer ce dossier.');
        }
    }

    private function assertWritable(ResolvedPath $folder): void
    {
        if ($folder->isVirtualRoot) {
            throw new FileManagerException('Ouvrez d\'abord un dossier.');
        }
        if ($folder->readOnly) {
            throw new FileManagerException('Ce dossier est en lecture seule : il est géré depuis la fiche du licencié.');
        }
        if ($folder->real === null || !is_dir($folder->real)) {
            throw new FileManagerException('Dossier introuvable.');
        }
    }

    private function assertModifiable(ResolvedPath $item): void
    {
        if ($item->isVirtualRoot || $item->real === null || !$item->exists) {
            throw new FileManagerException('Élément introuvable.');
        }
        if ($item->readOnly) {
            throw new FileManagerException('Ce dossier est en lecture seule : il est géré depuis la fiche du licencié.');
        }
        if ($item->isDeclared) {
            throw new FileManagerException('Ce dossier est utilisé par le site : il ne peut être ni supprimé ni renommé (son contenu, oui).');
        }
    }

    private function uniqueName(string $dir, string $name): string
    {
        if (!file_exists($dir.\DIRECTORY_SEPARATOR.$name)) {
            return $name;
        }
        $base = pathinfo($name, \PATHINFO_FILENAME);
        $ext  = pathinfo($name, \PATHINFO_EXTENSION);
        for ($i = 2; $i < 1000; ++$i) {
            $candidate = sprintf('%s (%d)%s', $base, $i, $ext !== '' ? '.'.$ext : '');
            if (!file_exists($dir.\DIRECTORY_SEPARATOR.$candidate)) {
                return $candidate;
            }
        }

        return uniqid($base.'-').($ext !== '' ? '.'.$ext : '');
    }

    /** Type MIME à annoncer pour un fichier servi par l'application. */
    public function mimeOf(string $path): string
    {
        $ext = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        if (in_array($ext, ['txt', 'md', 'log', 'csv'], true)) {
            return 'text/plain; charset=UTF-8';
        }

        return MimeTypes::getDefault()->getMimeTypes($ext)[0] ?? 'application/octet-stream';
    }
}

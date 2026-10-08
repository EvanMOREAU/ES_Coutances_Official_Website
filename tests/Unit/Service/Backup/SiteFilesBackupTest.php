<?php

namespace App\Tests\Unit\Service\Backup;

use App\Service\Backup\SiteFilesBackup;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SiteFilesBackupTest extends TestCase
{
    private string $project;
    private SiteFilesBackup $backup;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/escoutances-bk-'.bin2hex(random_bytes(4));
        $files = [
            'src/Kernel.php'               => 'v1 kernel',
            'templates/base.html.twig'     => 'v1 base',
            'vendor/acme/lib/Lib.php'      => 'v1 lib',
            '.env'                         => 'APP_ENV=prod',
            '.env.local'                   => 'SECRET=1',
            '.git/HEAD'                    => 'ref: refs/heads/main',
            'var/cache/prod/x'             => 'cache',
            'public/uploads/photo.jpg'     => 'jpg',
            'public/images/logo.png'       => 'png',
            'public/assets/app.css'        => 'css',
            'public/index.php'             => 'v1 index',
        ];
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->project.'/'.$path), 0775, true);
            file_put_contents($this->project.'/'.$path, $content);
        }
        $this->backup = new SiteFilesBackup($this->project);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->project);
    }

    /** @return list<string> */
    private function entries(string $file): array
    {
        $zip = new \ZipArchive();
        $zip->open($this->project.'/var/backups/site/'.$file);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);

        return $names;
    }

    public function testArchiveContainsTheSiteButNeitherSecretsNorData(): void
    {
        $result = $this->backup->create('abc1234');

        self::assertMatchesRegularExpression('/^site-\d{8}-\d{6}-abc1234\.zip$/', $result['file']);
        self::assertSame(
            ['.env', 'public/index.php', 'src/Kernel.php', 'templates/base.html.twig', 'vendor/acme/lib/Lib.php'],
            $this->entries($result['file']),
            'Ni .git, ni .env.local, ni var/, ni les envois, les images ou les assets compilés ne doivent être archivés.',
        );
        self::assertSame(5, $result['files']);
        self::assertGreaterThan(0, $result['size']);
    }

    public function testRestoreBringsBackFilesAndRemovesWhatTheUpdateAddedToVendor(): void
    {
        $result = $this->backup->create();

        // La « mise à jour » modifie du code, supprime un fichier, ajoute une dépendance, touche aux données.
        file_put_contents($this->project.'/src/Kernel.php', 'v2 kernel');
        unlink($this->project.'/templates/base.html.twig');
        file_put_contents($this->project.'/vendor/acme/lib/Lib.php', 'v2 lib');
        mkdir($this->project.'/vendor/nouveau/paquet', 0775, true);
        file_put_contents($this->project.'/vendor/nouveau/paquet/P.php', 'new');
        file_put_contents($this->project.'/public/uploads/photo.jpg', 'jpg modifié');
        file_put_contents($this->project.'/.env.local', 'SECRET=2');

        $restored = $this->backup->restore($result['file']);

        self::assertSame(5, $restored);
        self::assertSame('v1 kernel', file_get_contents($this->project.'/src/Kernel.php'));
        self::assertSame('v1 base', file_get_contents($this->project.'/templates/base.html.twig'));
        self::assertSame('v1 lib', file_get_contents($this->project.'/vendor/acme/lib/Lib.php'));
        self::assertFileDoesNotExist($this->project.'/vendor/nouveau/paquet/P.php', 'Une dépendance ajoutée par la mise à jour doit disparaître.');
        self::assertDirectoryDoesNotExist($this->project.'/vendor/nouveau');
        self::assertSame('jpg modifié', file_get_contents($this->project.'/public/uploads/photo.jpg'), 'Les fichiers envoyés ne sont jamais touchés.');
        self::assertSame('SECRET=2', file_get_contents($this->project.'/.env.local'), 'La configuration locale n\'est jamais touchée.');
    }

    public function testDeleteRemovesOnlyAValidlyNamedArchive(): void
    {
        $result = $this->backup->create();

        self::assertFalse($this->backup->delete('../../.env'));
        self::assertFileExists($this->project.'/.env');
        self::assertTrue($this->backup->delete($result['file']));
        self::assertFileDoesNotExist($result['path']);
        self::assertFalse($this->backup->delete($result['file']), 'Déjà supprimée.');
    }

    public function testRestoreOfAMissingArchiveFails(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->backup->restore('site-20260101-000000.zip');
    }
}

<?php

namespace App\Tests\Unit\Service\FileManager;

use App\Service\FileManager\FileManagerException;
use App\Service\FileManager\FileStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileStorageTest extends TestCase
{
    private string $project;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/escoutances-fs-'.bin2hex(random_bytes(4));
        foreach (['public/images', 'public/uploads/articles', 'public/uploads/contrats-partenaires', 'var/documents'] as $dir) {
            mkdir($this->project.'/'.$dir, 0775, true);
        }
        $this->storage = new FileStorage($this->project);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->project);
    }

    private function upload(string $name, string $content = 'contenu'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNames(string $name): void
    {
        self::assertFalse($this->storage->isValidName($name));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'vide' => [''];
        yield 'point' => ['.'];
        yield 'parent' => ['..'];
        yield 'caché' => ['.htaccess'];
        yield 'slash' => ['a/b'];
        yield 'antislash' => ['a\\b'];
        yield 'caractère de contrôle' => ["a\x00b"];
        yield 'deux-points' => ['c:fichier'];
        yield 'trop long' => [str_repeat('a', 151)];
    }

    public function testValidNames(): void
    {
        self::assertTrue($this->storage->isValidName('Photo été 2026.jpg'));
        self::assertTrue($this->storage->isValidName('a'));
    }

    public function testSanitizeName(): void
    {
        self::assertSame('a b', $this->storage->sanitizeName('a/b'));
        self::assertSame('nom.pdf', $this->storage->sanitizeName('  nom.pdf. '));
        self::assertSame(150, mb_strlen($this->storage->sanitizeName(str_repeat('é', 400))));
    }

    #[DataProvider('traversalAttempts')]
    public function testPathTraversalIsRejected(string $virtual): void
    {
        $this->expectException(FileManagerException::class);

        $this->storage->resolve($virtual);
    }

    /** @return iterable<string, array{string}> */
    public static function traversalAttempts(): iterable
    {
        yield 'remontée' => ['images/../../etc'];
        yield 'remontée directe' => ['../secret'];
        yield 'antislash' => ['images\\..\\..\\secret'];
        yield 'fichier caché' => ['images/.env'];
        yield 'racine inconnue' => ['inconnu'];
        yield 'octet nul' => ["images/a\0b"];
    }

    public function testResolveRootAndFolders(): void
    {
        $root = $this->storage->resolve('');
        self::assertTrue($root->isVirtualRoot);
        self::assertNull($root->real);

        $images = $this->storage->resolve('images');
        self::assertSame('images', $images->virtual);
        self::assertTrue($images->isDeclared);
        self::assertFalse($images->isPrivate);
        self::assertSame(FileStorage::FILTER_IMAGES, $images->filter);

        $documents = $this->storage->resolve('/documents/');
        self::assertTrue($documents->isPrivate);
    }

    public function testResolveMissingMountGivesAnEmptyFolderInsteadOfAnError(): void
    {
        rmdir($this->project.'/public/uploads/articles');

        $resolved = $this->storage->resolve('images/articles');

        self::assertNull($resolved->real, "Le dossier d'envoi pas encore créé se comporte comme un dossier vide.");
        self::assertTrue($resolved->isDeclared);
    }

    public function testResolveMissingFolderIsFlaggedAsAbsent(): void
    {
        $resolved = $this->storage->resolve('fichiers-du-site/n-existe-pas/aussi');

        self::assertFalse($resolved->exists);
    }

    public function testCreateRenameAndDeleteFolder(): void
    {
        // Les dossiers de premier niveau de « documents » sont les espaces des comptes : on travaille dessous.
        mkdir($this->project.'/var/documents/compte-1');
        $parent = $this->storage->resolve('documents/compte-1');

        $virtual = $this->storage->createFolder($parent, 'Contrats 2026');
        self::assertSame('documents/compte-1/Contrats 2026', $virtual);
        self::assertDirectoryExists($this->project.'/var/documents/compte-1/Contrats 2026');

        $renamed = $this->storage->rename($this->storage->resolve($virtual), 'Archives');
        self::assertSame('documents/compte-1/Archives', $renamed);
        self::assertDirectoryExists($this->project.'/var/documents/compte-1/Archives');

        $this->storage->delete($this->storage->resolve($renamed));
        self::assertDirectoryDoesNotExist($this->project.'/var/documents/compte-1/Archives');
    }

    public function testCreateFolderRejectsDuplicates(): void
    {
        $parent = $this->storage->resolve('documents');
        $this->storage->createFolder($parent, 'Doublon');

        $this->expectException(FileManagerException::class);
        $this->storage->createFolder($parent, 'Doublon');
    }

    public function testUploadStoresAllowedFileAndAvoidsOverwriting(): void
    {
        $parent = $this->storage->resolve('documents');

        $first = $this->storage->upload($parent, $this->upload('rapport.pdf', 'un'));
        $second = $this->storage->upload($parent, $this->upload('rapport.pdf', 'deux'));

        self::assertSame('documents/rapport.pdf', $first);
        self::assertNotSame($first, $second, 'Un second envoi du même nom ne doit pas écraser le premier.');
        self::assertSame('un', file_get_contents($this->project.'/var/documents/rapport.pdf'));
    }

    #[DataProvider('forbiddenUploads')]
    public function testUploadRejectsDangerousFiles(string $name): void
    {
        $this->expectException(FileManagerException::class);

        $this->storage->upload($this->storage->resolve('documents'), $this->upload($name));
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenUploads(): iterable
    {
        yield 'php' => ['shell.php'];
        yield 'phtml' => ['shell.phtml'];
        yield 'exécutable' => ['virus.exe'];
        yield 'double extension' => ['image.jpg.php'];
        yield 'sans extension' => ['README'];
        yield 'caché' => ['.htaccess'];
    }

    public function testImageFolderRefusesNonImages(): void
    {
        $this->expectException(FileManagerException::class);

        $this->storage->upload($this->storage->resolve('images'), $this->upload('document.pdf'));
    }

    public function testFilesFolderRefusesImages(): void
    {
        $this->expectException(FileManagerException::class);

        $this->storage->upload($this->storage->resolve('fichiers-du-site'), $this->upload('photo.png'));
    }

    public function testRenameCannotChangeTheExtension(): void
    {
        $path = $this->storage->upload($this->storage->resolve('documents'), $this->upload('note.txt'));

        $this->expectException(FileManagerException::class);
        $this->storage->rename($this->storage->resolve($path), 'note.php');
    }

    public function testDeclaredFoldersCannotBeDeletedOrRenamed(): void
    {
        $articles = $this->storage->resolve('images/articles');

        try {
            $this->storage->delete($articles);
            self::fail('Un dossier d\'envoi déclaré ne doit pas pouvoir être supprimé.');
        } catch (FileManagerException) {
        }

        $this->expectException(FileManagerException::class);
        $this->storage->rename($articles, 'autre');
    }

    public function testKindOfExtension(): void
    {
        self::assertSame('image', $this->storage->kindOf('png'));
        self::assertSame('pdf', $this->storage->kindOf('pdf'));
        self::assertSame('sheet', $this->storage->kindOf('xlsx'));
    }

    public function testListingShowsCreatedItems(): void
    {
        $parent = $this->storage->resolve('documents');
        $this->storage->createFolder($parent, 'Dossier');
        $this->storage->upload($parent, $this->upload('a.txt'));

        $names = array_column($this->storage->listing($parent), 'name');

        self::assertContains('Dossier', $names);
        self::assertContains('a.txt', $names);
    }

    public function testUsageCountsFiles(): void
    {
        $this->storage->upload($this->storage->resolve('documents'), $this->upload('a.txt', str_repeat('x', 1000)));

        $usage = $this->storage->usage(10_000_000);

        self::assertGreaterThanOrEqual(1000, $usage['used']);
        self::assertSame(10_000_000, $usage['quota']);
    }
}

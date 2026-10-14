<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use Twig\Environment;

/** Filtre Twig |thumb : miniatures WebP à la demande pour les rasters, original pour le reste. */
final class ThumbnailTest extends DatabaseTestCase
{
    private string $dir = '';

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        if ('' !== $this->dir && is_dir($this->dir)) {
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function render(string $path, string $filter = 'carte'): string
    {
        return static::getContainer()->get(Environment::class)->createTemplate('{{ path|thumb(filter) }}')->render(['path' => $path, 'filter' => $filter]);
    }

    private function createImage(string $name): string
    {
        $this->dir = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/tests-miniatures';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        $image = imagecreatetruecolor(1200, 800);
        imagepng($image, $this->dir.'/'.$name);

        return 'uploads/tests-miniatures/'.$name;
    }

    public function testRasterImageGetsAResizedWebpAddress(): void
    {
        $path = $this->createImage('photo.png');

        $url = $this->render($path, 'carte');

        self::assertStringContainsString('/media/cache/resolve/carte/'.$path, $url);
    }

    public function testThumbnailIsGeneratedSmallerThanTheOriginal(): void
    {
        $path = $this->createImage('grande.png');
        $url  = $this->render($path, 'mini');

        $this->client->request('GET', parse_url($url, PHP_URL_PATH) ?: '/');

        self::assertResponseRedirects();
        $cached = (string) $this->client->getResponse()->headers->get('Location');
        $file   = static::getContainer()->getParameter('kernel.project_dir').'/public'.parse_url($cached, PHP_URL_PATH);
        self::assertFileExists($file);
        $size = getimagesize($file) ?: [0];
        self::assertSame(96, $size[0]);
        @unlink($file);
    }

    public function testVectorAndMissingFilesAreServedAsIs(): void
    {
        $this->createImage('logo.svg');

        self::assertSame('/uploads/tests-miniatures/logo.svg', $this->render('uploads/tests-miniatures/logo.svg'));
        self::assertSame('/uploads/inexistant/absent.jpg', $this->render('uploads/inexistant/absent.jpg'));
    }
}

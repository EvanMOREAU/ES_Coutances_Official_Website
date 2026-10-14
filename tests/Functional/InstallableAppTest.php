<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;

/** Application installable : manifeste valide, icônes présentes, service worker sans mise en cache de données privées. */
final class InstallableAppTest extends DatabaseTestCase
{
    private const PUBLIC_DIR = __DIR__.'/../../public';

    public function testManifestIsValidAndItsIconsExist(): void
    {
        $manifest = json_decode((string) file_get_contents(self::PUBLIC_DIR.'/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('standalone', $manifest['display']);
        self::assertSame('/', $manifest['start_url']);
        self::assertNotEmpty($manifest['name']);
        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $file = self::PUBLIC_DIR.$icon['src'];
            self::assertFileExists($file);
            $size = getimagesize($file) ?: [0, 0];
            self::assertSame($icon['sizes'], $size[0].'x'.$size[1]);
            $sizes[] = $icon['sizes'];
        }
        self::assertContains('192x192', $sizes);
        self::assertContains('512x512', $sizes);
    }

    public function testServiceWorkerNeverCachesPagesOrPrivateAreas(): void
    {
        $worker = (string) file_get_contents(self::PUBLIC_DIR.'/sw.js');

        self::assertStringContainsString("request.method !== 'GET'", $worker);
        self::assertStringNotContainsString('/admin', preg_replace('#/\*.*?\*/#s', '', $worker) ?? '');
        self::assertStringNotContainsString('/mon-compte', preg_replace('#/\*.*?\*/#s', '', $worker) ?? '');
        self::assertStringContainsString("request.mode === 'navigate'", $worker);
        self::assertFileExists(self::PUBLIC_DIR.'/offline.html');
    }

    public function testPublicPagesAdvertiseTheManifestAndRegisterTheWorker(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSame('/manifest.webmanifest', $crawler->filter('link[rel="manifest"]')->attr('href'));
        self::assertSame('#C8102E', $crawler->filter('meta[name="theme-color"]')->attr('content'));
        self::assertStringContainsString('sw-register', (string) $this->client->getResponse()->getContent());
    }
}

<?php

namespace App\Tests\Unit\Service;

use App\Service\Upload\ImageCleaner;
use PHPUnit\Framework\TestCase;

final class ImageCleanerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/imgclean-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testPngIsReencodedWithoutTrailingPayloadAndKeepsItsSize(): void
    {
        if (!\extension_loaded('gd')) {
            self::markTestSkipped('gd requis');
        }
        $image = imagecreatetruecolor(20, 10);
        imagesavealpha($image, true);
        $path = $this->dir.'/a.png';
        imagepng($image, $path);
        file_put_contents($path, '<?php echo "pwned"; ?>', \FILE_APPEND);   // contenu greffé après l'image

        self::assertTrue((new ImageCleaner())->clean($path));

        self::assertStringNotContainsString('pwned', (string) file_get_contents($path));
        $size = getimagesize($path);
        self::assertIsArray($size);
        self::assertSame([20, 10], [$size[0], $size[1]]);
        self::assertFileDoesNotExist($path.'.clean');
    }

    public function testNonImagesAndUnknownFilesAreLeftAlone(): void
    {
        $text = $this->dir.'/a.txt';
        file_put_contents($text, 'bonjour');

        self::assertFalse((new ImageCleaner())->clean($text));
        self::assertFalse((new ImageCleaner())->clean($this->dir.'/absent.png'));
        self::assertSame('bonjour', file_get_contents($text));
    }
}

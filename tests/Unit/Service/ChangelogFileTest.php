<?php

namespace App\Tests\Unit\Service;

use App\Service\ChangelogFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ChangelogFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/escoutances-changelog-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/changelog', 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testMissingFileGivesNoRelease(): void
    {
        self::assertSame([], (new ChangelogFile($this->dir))->releases());
    }

    public function testReleasesAreSortedMostRecentFirstAndCounted(): void
    {
        file_put_contents($this->dir.'/changelog/releases.yaml', <<<'YAML'
            - version: "1.0.0"
              date: "2026-01-01"
              added: ["Première version"]
            - version: "1.1.0"
              date: "2026-03-01"
              titre: "Mars"
              added: ["A", "B"]
              fixed: ["C"]
              removed: []
            - nom: "entrée sans version"
            YAML);

        $releases = (new ChangelogFile($this->dir))->releases();

        self::assertCount(2, $releases);
        self::assertSame('v1.1.0', $releases[0]['label']);
        self::assertSame('Mars', $releases[0]['titre']);
        self::assertSame(3, $releases[0]['count']);
        self::assertSame(['added', 'fixed'], array_keys($releases[0]['groups']), 'Les rubriques vides sont omises.');
        self::assertSame('v1.0.0', $releases[1]['label']);
    }

    public function testTheRealChangelogIsWellFormed(): void
    {
        $releases = (new ChangelogFile(dirname(__DIR__, 3)))->releases();

        self::assertNotEmpty($releases);
        $versions = array_column($releases, 'version');
        self::assertSame($versions, array_values(array_unique($versions)), 'Chaque version ne doit apparaître qu\'une fois.');
        foreach ($releases as $release) {
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $release['version']);
            self::assertGreaterThan(0, $release['count'], sprintf('La version %s ne contient aucune ligne.', $release['version']));
        }
    }
}

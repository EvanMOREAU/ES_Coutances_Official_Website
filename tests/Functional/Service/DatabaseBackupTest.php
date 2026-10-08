<?php

namespace App\Tests\Functional\Service;

use App\Entity\User;
use App\Service\Backup\DatabaseBackup;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Seule l'écriture est testée ici : une restauration supprime des tables (DDL, validé implicitement par
 * MySQL), ce qui casserait l'isolation par transaction des tests. Son refus d'un fichier invalide l'est.
 */
final class DatabaseBackupTest extends DatabaseTestCase
{
    private string $project;
    private DatabaseBackup $backup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = sys_get_temp_dir().'/escoutances-db-'.bin2hex(random_bytes(4));
        $this->backup  = new DatabaseBackup($this->em->getConnection(), $this->project);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->project);
        parent::tearDown();
    }

    /** @return list<string> */
    private function lines(string $path): array
    {
        $gz = gzopen($path, 'rb');
        $lines = [];
        while (false !== ($line = gzgets($gz))) {
            $lines[] = rtrim($line, "\r\n");
        }
        gzclose($gz);

        return $lines;
    }

    public function testBackupIsACompleteSqlScriptOfTheCurrentData(): void
    {
        $user = $this->createUser(['ROLE_EDITOR'], 'sauvegarde-test@test.local');
        $user->setNom("Nom avec 'apostrophe' et\nretour à la ligne");
        $this->em->flush();

        $result = $this->backup->create(DatabaseBackup::KIND_MANUAL, 'abc1234');

        self::assertMatchesRegularExpression('/^manuel-\d{8}-\d{6}-abc1234\.sql\.gz$/', $result['file']);
        $lines = $this->lines($result['path']);
        self::assertSame('-- escoutances-backup v1', $lines[0]);
        self::assertSame('-- fin de la sauvegarde', end($lines));
        self::assertContains('DROP TABLE IF EXISTS `user`;', $lines);

        $inserts = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'INSERT INTO `user` ')));
        self::assertNotEmpty($inserts);
        self::assertStringContainsString('sauvegarde-test@test.local', implode("\n", $inserts));
        // Une instruction par ligne : le retour à la ligne de la valeur est échappé.
        self::assertStringContainsString('retour à la ligne', implode("\n", $inserts));
        self::assertStringContainsString("apostrophe\\' et\\nretour", implode("\n", $inserts));
    }

    public function testListingAndLookupAreRestrictedToValidNames(): void
    {
        $result = $this->backup->create(DatabaseBackup::KIND_UPDATE, 'abc1234');

        self::assertSame($result['file'], $this->backup->all()[0]['file']);
        self::assertSame('maj', $this->backup->all()[0]['kind']);
        self::assertSame($result['path'], $this->backup->path($result['file']));
        self::assertNull($this->backup->path('../../.env'));
        self::assertNull($this->backup->path('maj-20260101-000000.sql.gz'), 'Fichier inexistant.');
        self::assertNull($this->backup->path('..%2f..%2f.env'));
    }

    public function testOnlyAutomaticBackupsArePruned(): void
    {
        $dir = $this->backup->directory();
        foreach (['auto-20260101-000001', 'auto-20260101-000002', 'auto-20260101-000003', 'maj-20260101-000001', 'manuel-20260101-000001'] as $i => $name) {
            file_put_contents($dir.'/'.$name.'.sql.gz', 'x');
            touch($dir.'/'.$name.'.sql.gz', 1_700_000_000 + $i);
        }

        $removed = $this->backup->pruneAutomatic(1);

        self::assertSame(2, $removed);
        $left = array_column($this->backup->all(), 'file');
        sort($left);
        self::assertSame(['auto-20260101-000003.sql.gz', 'maj-20260101-000001.sql.gz', 'manuel-20260101-000001.sql.gz'], $left, 'Les sauvegardes avant mise à jour et manuelles ne sont jamais supprimées.');
    }

    public function testRestoreRefusesAnIncompleteFileWithoutTouchingTheDatabase(): void
    {
        $this->createUser(['ROLE_EDITOR'], 'toujours-la@test.local');
        $path = $this->backup->directory().'/auto-20260101-000000.sql.gz';
        $gz = gzopen($path, 'wb');
        gzwrite($gz, "-- escoutances-backup v1\nDROP TABLE IF EXISTS `user`;\n"); // pas de marqueur de fin
        gzclose($gz);

        try {
            $this->backup->restore($path);
            self::fail('Un fichier tronqué ne doit pas être restauré.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('incomplète', $e->getMessage());
        }

        self::assertNotNull($this->em->getRepository(User::class)->findOneBy(['email' => 'toujours-la@test.local']));
    }
}

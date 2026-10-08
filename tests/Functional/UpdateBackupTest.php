<?php

namespace App\Tests\Functional;

use App\Entity\Deployment;
use App\Security\PermissionCatalog;
use App\Tests\Support\DatabaseTestCase;

/** Sauvegardes autour des mises à jour : accès réservé au développeur, suppression validée de l'archive des fichiers. */
final class UpdateBackupTest extends DatabaseTestCase
{
    /** @var list<string> fichiers créés dans le vrai dossier var/backups, supprimés en fin de test */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function backupDir(string $kind): string
    {
        $dir = static::getContainer()->getParameter('kernel.project_dir').'/var/backups/'.$kind;
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    public function testDatabaseBackupsAreReservedToTheDeveloperRoleWhateverThePermissions(): void
    {
        $file = 'manuel-20260101-000000.sql.gz';
        file_put_contents($this->backupDir('db').'/'.$file, 'x');
        $this->created[] = $this->backupDir('db').'/'.$file;

        // Administrateur sans restriction, puis éditeur à qui on accorde TOUTES les autorisations du catalogue.
        $admin = $this->createUser(['ROLE_ADMIN']);
        $editor = $this->createUser(['ROLE_EDITOR'])->setAccesRestreint(true)->setPermissionsAjoutees(PermissionCatalog::all());
        $this->em->flush();

        foreach ([$admin, $editor] as $user) {
            $this->loginAs([], $user);
            $this->client->request('GET', '/admin/developpeur/sauvegardes');
            self::assertResponseStatusCodeSame(403);
            $this->client->request('GET', '/admin/developpeur/sauvegardes/'.$file.'/telecharger');
            self::assertResponseStatusCodeSame(403);
            $this->client->request('POST', '/admin/developpeur/sauvegardes/creer', ['_token' => 'x']);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testDeveloperCanExtractAndDownloadTheDatabase(): void
    {
        $this->loginAsDev();
        $before = glob($this->backupDir('db').'/manuel-*.sql.gz') ?: [];

        $crawler = $this->client->request('GET', '/admin/developpeur/sauvegardes');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Extraire la base maintenant')->form());
        self::assertResponseRedirects('/admin/developpeur/sauvegardes');

        $new = array_values(array_diff(glob($this->backupDir('db').'/manuel-*.sql.gz') ?: [], $before));
        $this->created = [...$this->created, ...$new];
        self::assertCount(1, $new);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', basename($new[0]));
        $link = $crawler->selectLink('Télécharger')->first()->link();
        $this->client->click($link);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testDownloadRefusesNamesThatAreNotBackups(): void
    {
        $this->loginAsDev();

        $this->client->request('GET', '/admin/developpeur/sauvegardes/manuel-20260101-000000.sql.gz/telecharger');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/developpeur/sauvegardes/.env/telecharger');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePendingEndpointReturnsTheCountForDevelopers(): void
    {
        $this->loginAsDev();

        $this->client->request('GET', '/admin/mise-a-jour/etat');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsInt($data['pending']);
        self::assertFalse($data['running']);
    }

    public function testTheFilesBackupIsDeletedOnlyOnValidationAndTheDatabaseBackupIsKept(): void
    {
        $this->loginAsDev();
        $filesFile = 'site-20260101-000000-abc1234.zip';
        $dbFile = 'maj-20260101-000000-abc1234.sql.gz';
        file_put_contents($this->backupDir('site').'/'.$filesFile, 'zip');
        file_put_contents($this->backupDir('db').'/'.$dbFile, 'sql');
        $this->created = [$this->backupDir('site').'/'.$filesFile, $this->backupDir('db').'/'.$dbFile];

        $deployment = (new Deployment())->setStatus(Deployment::STATUS_SUCCESS)->setDbBackup($dbFile)->setFilesBackup($filesFile)->setFilesBackupSize(3);
        $this->em->persist($deployment);
        $this->em->flush();
        $url = sprintf('/admin/mise-a-jour/%d/sauvegarde/supprimer', $deployment->getId());

        $crawler = $this->client->request('GET', '/admin/mise-a-jour');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Base conservée');
        $token = (string) $crawler->filter('[data-controller=deploy]')->attr('data-deploy-token-value');

        $this->client->request('POST', $url, server: ['HTTP_X_CSRF_TOKEN' => 'faux']);
        self::assertResponseStatusCodeSame(403);
        self::assertFileExists($this->backupDir('site').'/'.$filesFile);

        $this->client->request('POST', $url, server: ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertFileDoesNotExist($this->backupDir('site').'/'.$filesFile);
        self::assertFileExists($this->backupDir('db').'/'.$dbFile, 'La sauvegarde de la base n\'est jamais supprimée.');
        $this->em->clear();
        $reloaded = $this->em->find(Deployment::class, $deployment->getId());
        self::assertFalse($reloaded->hasFilesBackup());
        self::assertNotNull($reloaded->getFilesBackupDeletedAt());

        $this->client->request('POST', $url, server: ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(409);
    }
}

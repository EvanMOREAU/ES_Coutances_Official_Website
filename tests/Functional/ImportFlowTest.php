<?php

namespace App\Tests\Functional;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\FootClubSpreadsheet;

final class ImportFlowTest extends DatabaseTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @param list<list<string|int|null>> $rows */
    private function upload(array $rows): void
    {
        $this->files[] = $file = FootClubSpreadsheet::create($rows);
        $crawler = $this->client->request('GET', '/admin/import');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/admin/import/apercu"]')->form();
        $form['fichier'] = $file;
        $this->client->submit($form);
    }

    /** @return list<list<string|int|null>> */
    private function family(): array
    {
        return [
            [90001, 'L1', 'IMPORTTEST', 'Lucas', '12/04/2014', '50200', 'Libre', '0612345678', 'parent-import@test.local', 'IMPORTTEST Paul', '1 rue de la Gare', '50200', 'parent-import@test.local'],
            [90002, 'L2', 'IMPORTTEST', 'Léa', '03/09/2016', '50200', 'Libre', null, null, 'IMPORTTEST Paul', '1 rue de la Gare', '50200', 'parent-import@test.local'],
        ];
    }

    public function testPreviewThenConfirmCreatesTheFamilyAndItsLicensees(): void
    {
        $this->createSaison('2099-2100', '2099-07-01', '2100-06-30', true);
        $this->loginAsDev();

        $this->upload($this->family());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'IMPORTTEST');
        self::assertSame(0, $this->em->getRepository(Licencie::class)->count(['numeroPersonne' => '90001']), 'L\'aperçu n\'écrit encore rien en base.');

        $this->client->submit($this->client->getCrawler()->filter('form[action$="/admin/import/confirmer"]')->form());

        self::assertResponseRedirects('/admin/familles');
        $this->em->clear();
        $lucas = $this->em->getRepository(Licencie::class)->findOneBy(['numeroPersonne' => '90001']);
        self::assertNotNull($lucas);
        self::assertSame('IMPORTTEST', $lucas->getNom());
        self::assertSame('2099-2100', $lucas->getSaison()?->getLibelle());
        self::assertSame($lucas->getFamille(), $this->em->getRepository(Licencie::class)->findOneBy(['numeroPersonne' => '90002'])?->getFamille(), 'Les deux enfants partagent la même famille.');
        self::assertInstanceOf(Famille::class, $lucas->getFamille());
    }

    public function testImportingTheSameFileTwiceDoesNotDuplicate(): void
    {
        $this->createSaison('2099-2100', '2099-07-01', '2100-06-30', true);
        $this->loginAsDev();

        for ($i = 0; $i < 2; ++$i) {
            $this->upload($this->family());
            $this->client->submit($this->client->getCrawler()->filter('form[action$="/admin/import/confirmer"]')->form());
            self::assertResponseRedirects('/admin/familles');
        }

        self::assertSame(1, $this->em->getRepository(Licencie::class)->count(['numeroPersonne' => '90001']));
        self::assertSame(1, $this->em->getRepository(Licencie::class)->count(['numeroPersonne' => '90002']));
    }

    public function testImportWithoutActiveSeasonIsRefusedWithAMessage(): void
    {
        $this->loginAsDev();
        foreach ($this->em->getRepository(\App\Entity\Saison::class)->findAll() as $saison) {
            $saison->setActive(false);
        }
        $this->em->flush();

        $this->upload($this->family());
        $this->client->submit($this->client->getCrawler()->filter('form[action$="/admin/import/confirmer"]')->form());

        self::assertResponseRedirects('/admin/import');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'saison active');
        self::assertSame(0, $this->em->getRepository(Licencie::class)->count(['numeroPersonne' => '90001']));
    }

    public function testNonSpreadsheetIsRejectedWithoutAnError(): void
    {
        $this->loginAsDev();
        $this->files[] = $file = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        file_put_contents($file, 'pas un classeur');
        $crawler = $this->client->request('GET', '/admin/import');
        $form = $crawler->filter('form[action$="/admin/import/apercu"]')->form();
        $form['fichier'] = $file;

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/import');
    }

    public function testConfirmWithAnInvalidCsrfTokenIsRefused(): void
    {
        $this->loginAsDev();

        $this->client->request('POST', '/admin/import/confirmer', ['_token' => 'jeton-invalide']);

        self::assertResponseRedirects('/admin/import');
        self::assertSame(0, $this->em->getRepository(Licencie::class)->count(['numeroPersonne' => '90001']));
    }

    public function testEditorWithoutImportPermissionCannotImport(): void
    {
        $this->loginAs(['ROLE_EDITOR']);

        $this->client->request('GET', '/admin/import');

        self::assertResponseStatusCodeSame(403);
    }
}

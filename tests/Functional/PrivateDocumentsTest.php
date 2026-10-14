<?php

namespace App\Tests\Functional;

use App\Entity\ContratPartenaire;
use App\Entity\ContratPartenaireDocument;
use App\Entity\Partenaire;
use App\Tests\Support\DatabaseTestCase;

/** Les documents des contrats partenaires sont confidentiels : hors du dossier public, servis après contrôle des droits. */
final class PrivateDocumentsTest extends DatabaseTestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    private function createDocument(): ContratPartenaireDocument
    {
        $partenaire = (new Partenaire())->setNom('Garage Test');
        $contrat    = (new ContratPartenaire())->setPartenaire($partenaire)->setTitre('Contrat maillots');
        $name       = 'test-'.bin2hex(random_bytes(4)).'.pdf';
        $dir        = static::getContainer()->getParameter('kernel.project_dir').'/var/private/contrats-partenaires';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $this->file = $dir.'/'.$name;
        file_put_contents($this->file, "%PDF-1.4\ncontrat confidentiel");

        $document = (new ContratPartenaireDocument())->setFichierName($name)->setNomOriginal('contrat-signe.pdf');
        $contrat->addDocument($document);
        $this->em->persist($partenaire);
        $this->em->persist($contrat);
        $this->em->flush();

        return $document;
    }

    public function testDocumentIsServedToAuthorisedStaffOnly(): void
    {
        $document = $this->createDocument();

        $this->client->request('GET', '/admin/partenaires/contrats/documents/'.$document->getId().'/telecharger');
        self::assertResponseRedirects(null, null, 'Un visiteur non connecté est renvoyé vers la connexion.');

        $this->loginAsDev();
        $this->client->request('GET', '/admin/partenaires/contrats/documents/'.$document->getId().'/telecharger');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('nosniff', $this->client->getResponse()->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('contrat-signe.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testEditorWithoutPermissionCannotDownload(): void
    {
        $document = $this->createDocument();

        $this->loginAs(['ROLE_EDITOR']);
        $this->client->request('GET', '/admin/partenaires/contrats/documents/'.$document->getId().'/telecharger');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDocumentIsNotReachableFromThePublicFolder(): void
    {
        $document = $this->createDocument();

        $this->client->request('GET', '/uploads/contrats-partenaires/'.$document->getFichierName());

        self::assertResponseStatusCodeSame(404);
        $publicCopy = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/contrats-partenaires/'.$document->getFichierName();
        self::assertFileDoesNotExist($publicCopy);
    }

    public function testMissingFileGivesA404(): void
    {
        $document = $this->createDocument();
        unlink($this->file);

        $this->loginAsDev();
        $this->client->request('GET', '/admin/partenaires/contrats/documents/'.$document->getId().'/telecharger');

        self::assertResponseStatusCodeSame(404);
    }
}

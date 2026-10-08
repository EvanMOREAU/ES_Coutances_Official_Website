<?php

namespace App\Tests\Functional;

use App\Entity\PageContenu;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicPagesTest extends DatabaseTestCase
{
    #[DataProvider('publicUrls')]
    public function testPublicPageRenders(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful(sprintf('%s doit répondre 2xx.', $url));
    }

    /** @return iterable<string, array{string}> */
    public static function publicUrls(): iterable
    {
        foreach (['/', '/club/encadrement', '/contact', '/boutique', '/boutique/panier', '/admin/login', '/mon-compte/connexion', '/mon-compte/inscription', '/reset-password'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('contentPages')]
    public function testClubPageNeedsItsContent(string $url, string $slug): void
    {
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404, "Sans contenu en base, la page n'existe pas.");

        $this->em->persist((new PageContenu())->setSlug($slug)->setTitre('Titre de test')->setContenu('<p>Contenu de test</p>'));
        $this->em->flush();

        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Contenu de test');
    }

    /** @return iterable<string, array{string, string}> */
    public static function contentPages(): iterable
    {
        yield 'histoire' => ['/club/histoire', 'histoire'];
        yield 'infrastructure' => ['/club/infrastructure', 'infrastructure'];
        yield 'page libre' => ['/page/ma-page', 'ma-page'];
    }

    public function testUnknownPageIsA404(): void
    {
        $this->client->request('GET', '/page/cette-page-nexiste-pas');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownUrlIsA404(): void
    {
        $this->client->request('GET', '/url/qui/nexiste/pas');

        self::assertResponseStatusCodeSame(404);
    }

    public function testHomePageHasTitleAndLanguage(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('title')->count());
        self::assertStringContainsString('lang="fr"', (string) $this->client->getResponse()->getContent());
    }
}

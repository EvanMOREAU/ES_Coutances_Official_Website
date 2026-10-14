<?php

namespace App\Tests\Functional;

use App\Entity\PageContenu;
use App\Tests\Support\DatabaseTestCase;

final class PublicCacheAndSeoTest extends DatabaseTestCase
{
    public function testPersonalPagesAreNeverCacheable(): void
    {
        $this->client->request('GET', '/contact');

        self::assertStringNotContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testDedicatedPagesAreNotDuplicatedInSitemapAndRedirect(): void
    {
        $this->em->persist((new PageContenu())->setSlug('histoire')->setTitre('Notre histoire')->setContenu('<p>x</p>'));
        $this->em->persist((new PageContenu())->setSlug('partenaires-locaux')->setTitre('Partenaires locaux')->setContenu('<p>y</p>'));
        $this->em->flush();

        $this->client->request('GET', '/sitemap.xml');
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('/page/partenaires-locaux', $xml);
        self::assertStringNotContainsString('/page/histoire', $xml, '« histoire » a sa propre adresse, listée une seule fois.');

        $this->client->request('GET', '/page/histoire');
        self::assertResponseRedirects('/club/histoire', 301);
    }

    public function testArticleHasProductStructuredData(): void
    {
        $article = $this->createArticle('Maillot domicile', 3500, [3]);

        $crawler = $this->client->request('GET', '/boutique/article/'.$article->getSlug());

        self::assertResponseIsSuccessful();
        $json = json_decode($crawler->filter('script[type="application/ld+json"]')->first()->text('', false), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Product', $json['@type']);
        self::assertSame('35.00', $json['offers']['price']);
        self::assertSame('https://schema.org/InStock', $json['offers']['availability']);
    }

    public function testShareImagesAreLight(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertStringContainsString('images/og-image', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));
        self::assertSame('summary_large_image', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        self::assertLessThan(300_000, (int) filesize(__DIR__.'/../../public/images/og-image.png'));
        self::assertLessThan(100_000, (int) filesize(__DIR__.'/../../public/images/logo_hd_trans3_gt.png'));
    }
}

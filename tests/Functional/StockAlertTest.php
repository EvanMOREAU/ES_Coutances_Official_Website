<?php

namespace App\Tests\Functional;

use App\Entity\BoutiqueSettings;
use App\Repository\ArticleVarianteRepository;
use App\Tests\Support\DatabaseTestCase;

final class StockAlertTest extends DatabaseTestCase
{
    private function repository(): ArticleVarianteRepository
    {
        return static::getContainer()->get(ArticleVarianteRepository::class);
    }

    public function testOnlyLowStockSizesOfActiveArticlesAreReturned(): void
    {
        $this->createArticle('Maillot', 3500, [1, 10]);
        $archive = $this->createArticle('Ancien maillot', 3000, [0]);
        $archive->setActif(false);
        $this->em->flush();

        $low = $this->repository()->findStockBas(3);

        self::assertCount(1, $low);
        self::assertSame(1, $low[0]->getStock());
        self::assertSame('Maillot', $low[0]->getArticle()?->getNom());
    }

    public function testThresholdZeroDisablesTheAlert(): void
    {
        $this->createArticle('Maillot', 3500, [0]);

        self::assertSame([], $this->repository()->findStockBas(0));
    }

    public function testBellAnnouncesLowStockForPeopleWhoCanSeeArticles(): void
    {
        $this->createArticle('Écharpe', 1500, [2]);
        $this->loginAsDev();

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'en stock bas');
    }

    public function testThresholdIsEditableInTheShopSettings(): void
    {
        $this->loginAsDev();

        $crawler = $this->client->request('GET', '/admin/boutique/maintenance');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form(['form[seuilStockBas]' => '7']);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/boutique/maintenance');
        $this->em->clear();
        $settings = $this->em->getRepository(BoutiqueSettings::class)->findOneBy([]);
        self::assertSame(7, $settings?->getSeuilStockBas());
    }

    public function testNegativeThresholdIsRefused(): void
    {
        $this->loginAsDev();

        $crawler = $this->client->request('GET', '/admin/boutique/maintenance');
        $this->client->submit($crawler->filter('form')->form(['form[seuilStockBas]' => '-4']));

        self::assertResponseStatusCodeSame(422);
    }
}

<?php

namespace App\Tests\Functional;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Tests\Support\DatabaseTestCase;

/** Tableau de bord : indicateurs et graphiques selon les autorisations, avec des données réelles ou aucune. */
final class DashboardTest extends DatabaseTestCase
{
    public function testEmptyClubStillRendersEveryChartWithoutError(): void
    {
        $this->loginAsDev();

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        foreach (['licencies_evolution', 'effectifs_categories', 'licencies_genre', 'familles_tailles', 'paiements_encaisse', 'boutique_ventes', 'boutique_statuts', 'partenaires_evolution', 'contrats_encaissement', 'planning_prochains'] as $widget) {
            self::assertCount(1, $crawler->filter('[data-dashboard-target="widget"][data-widget="'.$widget.'"]'), $widget);
        }
        self::assertSelectorTextContains('body', 'Licenciés et familles');
    }

    public function testChartsReceiveTheClubFigures(): void
    {
        $saison = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $this->createLicencie($saison, 'Durand', 'Emma', '2015-05-02');
        $this->createLicencie($saison, 'Martin', 'Lucas', '2012-03-09');
        $this->loginAsDev();

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        $series = json_decode((string) $crawler->filter('[data-dashboard-target="widget"][data-widget="familles_tailles"] [data-controller="chart"]')->attr('data-chart-series-value'), true);
        self::assertSame([0, 2, 0, 0], $series[0]['values'], 'Deux familles d\'un licencié chacune.');
        $categories = json_decode((string) $crawler->filter('[data-dashboard-target="widget"][data-widget="effectifs_categories"] [data-controller="chart"]')->attr('data-chart-labels-value'), true);
        self::assertNotSame([], $categories);
        self::assertSame(2, $this->em->getRepository(Famille::class)->count([]));
        self::assertSame(2, $this->em->getRepository(Licencie::class)->count([]));
    }

    public function testEditorWithoutProfileOnlySeesWhatIsNotRestricted(): void
    {
        $this->loginAs(['ROLE_EDITOR']);

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-dashboard-target="widget"][data-widget="paiements_encaisse"], [data-dashboard-target="widget"][data-widget="boutique_ventes"], [data-dashboard-target="widget"][data-widget="licencies_evolution"]'));
        self::assertSelectorTextNotContains('body', 'Licenciés et familles');
    }

    public function testHiddenModulesStayAvailableToShowAgain(): void
    {
        $user = $this->loginAsDev();
        $user->setTablePreferences(['dashboard' => ['hidden' => ['licencies_genre']]]);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        $card = $crawler->filter('[data-dashboard-target="widget"][data-widget="licencies_genre"]');
        self::assertCount(1, $card);
        self::assertNotNull($card->attr('hidden'));
        self::assertNotSame('[]', (string) $card->filter('[data-controller="chart"]')->attr('data-chart-series-value'), 'Les données restent calculées : le module peut être réaffiché sans recharger.');
    }
}

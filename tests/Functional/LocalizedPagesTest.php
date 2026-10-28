<?php

namespace App\Tests\Functional;

use App\Service\Locale\Locales;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\BrowserKit\Cookie;

/** Les pages principales s'affichent dans chaque langue, sans clé de traduction brute ni phrase manquante dans l'interface. */
final class LocalizedPagesTest extends DatabaseTestCase
{
    private const KEY_LEAK = '/\b(?:dashboard|notif|settings|admin|portal|nav|footer|mail|lang|files)\.[a-z_]+(?:\.[a-z_]+)*\b/';

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        yield 'anglais' => ['en'];
        yield 'allemand' => ['de'];
    }

    /** @return list<string> */
    private static function adminPages(): array
    {
        return [
            '/admin', '/admin/notifications', '/admin/parametres/profil', '/admin/parametres/securite', '/admin/parametres/preferences',
            '/admin/parametres/apparence', '/admin/familles', '/admin/licencies', '/admin/paiements', '/admin/boutique/commandes',
            '/admin/boutique/articles', '/admin/partenaires', '/admin/partenaires/hub', '/admin/planning', '/admin/messagerie',
            '/admin/utilisateurs', '/admin/fichiers', '/admin/journal', '/admin/gestion-licencies', '/admin/site-vitrine',
            '/admin/configuration', '/admin/statistiques-effectifs', '/admin/saisons', '/admin/equipes', '/admin/clients-boutique',
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('locales')]
    public function testAdministrationPagesRender(string $locale): void
    {
        $user = $this->loginAsDev();
        $user->setLocale($locale);
        $this->em->flush();

        foreach (self::adminPages() as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url.' ('.$locale.')');
            self::assertSelectorExists('html[lang="'.$locale.'"]', $url);
            self::assertSame(0, preg_match(self::KEY_LEAK, $crawler->filter('body')->text('', false)), $url.' affiche une clé de traduction brute : '.($this->leak($crawler->filter('body')->text('', false))));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('locales')]
    public function testPublicAndAccountPagesRender(string $locale): void
    {
        $this->client->getCookieJar()->set(new Cookie(Locales::COOKIE, $locale));
        foreach (['/', '/contact', '/boutique', '/boutique/panier', '/mentions-legales', '/mon-compte/connexion', '/mon-compte/inscription', '/admin/login'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url.' ('.$locale.')');
            self::assertSelectorExists('html[lang="'.$locale.'"]', $url);
            self::assertSame(0, preg_match(self::KEY_LEAK, $crawler->filter('body')->text('', false)), $url.' : '.$this->leak($crawler->filter('body')->text('', false)));
        }

        $user = $this->loginAs(['ROLE_USER']);
        $user->setLocale($locale);
        $this->em->flush();
        foreach (['/mon-compte', '/mon-compte/commandes', '/mon-compte/parametres/profil', '/mon-compte/parametres/securite', '/mon-compte/parametres/preferences', '/mon-compte/parametres/apparence', '/mon-compte/parametres/confidentialite'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url.' ('.$locale.')');
            self::assertSame(0, preg_match(self::KEY_LEAK, $crawler->filter('body')->text('', false)), $url.' : '.$this->leak($crawler->filter('body')->text('', false)));
        }
    }

    private function leak(string $text): string
    {
        preg_match(self::KEY_LEAK, $text, $m);

        return $m[0] ?? '';
    }
}

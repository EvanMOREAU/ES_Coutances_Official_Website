<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Garde-fou : la CSP interdit les styles et scripts en ligne, les gabarits ne doivent donc plus en produire. */
final class StrictCspTest extends DatabaseTestCase
{
    public function testPolicyForbidsInlineStylesAndHandlers(): void
    {
        $this->client->request('GET', '/');
        $csp = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');

        self::assertMatchesRegularExpression("/style-src 'self' 'nonce-[\\w-]+'(;|$)/", $csp);
        self::assertStringContainsString("style-src-attr 'none'", $csp);
        self::assertStringNotContainsString("'unsafe-inline'", $csp);
        self::assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function testTurboVisitReusesTheNonceSentByTheBrowser(): void
    {
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');

        $crawler = $this->client->request('GET', '/', [], [], ['HTTP_X_CSP_NONCE' => $nonce]);

        self::assertStringContainsString("'nonce-$nonce'", (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertSame($nonce, $crawler->filter('meta[name="csp-nonce"]')->attr('content'));
    }

    public function testMalformedNonceHeaderIsIgnored(): void
    {
        $this->client->request('GET', '/', [], [], ['HTTP_X_CSP_NONCE' => "x'; script-src *"]);

        $csp = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringNotContainsString('script-src *', $csp);
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[\w-]{24}'/", $csp);
    }

    #[DataProvider('pages')]
    public function testPageHasNoInlineStyleOrScript(string $url, bool $admin): void
    {
        if ($admin) {
            $this->loginAsDev();
        }
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        self::assertSame(0, preg_match('/<[a-z][^>]*\sstyle="/i', $html), $url.' : attribut style="" interdit par la CSP.');
        self::assertSame(0, preg_match('/<style(?![^>]*nonce=)/i', $html), $url.' : <style> sans nonce.');
        self::assertSame(0, preg_match('/<script(?![^>]*(nonce=|src=|type="application\/ld\+json"|type="importmap"))[^>]*>/i', $html), $url.' : <script> en ligne sans nonce.');
        self::assertSame(0, preg_match('/\son(click|change|submit|load|input|error)=/i', $html), $url.' : gestionnaire d\'événement en ligne.');
    }

    /** @return iterable<string, array{string, bool}> */
    public static function pages(): iterable
    {
        foreach (['/', '/contact', '/boutique', '/boutique/panier', '/mentions-legales', '/admin/login', '/mon-compte/connexion', '/reset-password'] as $url) {
            yield 'public '.$url => [$url, false];
        }
        foreach (['/admin', '/admin/licencies', '/admin/familles', '/admin/equipes', '/admin/planning', '/admin/boutique/articles', '/admin/journal', '/admin/fichiers', '/admin/parametres/apparence'] as $url) {
            yield 'admin '.$url => [$url, true];
        }
    }
}

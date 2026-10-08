<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;

final class HardeningTest extends DatabaseTestCase
{
    /** @return iterable<string, array{string}> */
    public static function urls(): iterable
    {
        foreach (['/', '/contact', '/boutique', '/admin/login', '/mon-compte/connexion', '/url/inconnue', '/health'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('urls')]
    public function testEveryResponseCarriesTheSecurityHeaders(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'SAMEORIGIN');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHasHeader('Permissions-Policy');
        self::assertResponseNotHasHeader('X-Powered-By');
    }

    public function testHstsIsOnlySentOverHttps(): void
    {
        $this->client->request('GET', '/health');
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $this->client->request('GET', 'https://localhost/health', [], [], ['HTTPS' => 'on']);
        self::assertResponseHasHeader('Strict-Transport-Security');
    }

    public function testHealthEndpoint(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertSame('{"status":"ok"}', $this->client->getResponse()->getContent());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    #[DataProvider('errorTemplates')]
    public function testFriendlyErrorPagesRender(string $template, int $code, string $text): void
    {
        $html = static::getContainer()->get(Environment::class)->render($template, ['status_code' => $code, 'status_text' => $text]);

        self::assertStringContainsString((string) $code, $html);
        self::assertStringContainsString('Retour à l\'accueil', $html);
        self::assertStringNotContainsString('Exception', $html, 'Aucune trace technique ne doit être affichée.');
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function errorTemplates(): iterable
    {
        yield '404' => ['@Twig/Exception/error404.html.twig', 404, 'Not Found'];
        yield '403' => ['@Twig/Exception/error403.html.twig', 403, 'Forbidden'];
        yield '503' => ['@Twig/Exception/error503.html.twig', 503, 'Service Unavailable'];
        yield 'générique' => ['@Twig/Exception/error.html.twig', 500, 'Internal Server Error'];
    }

    public function testMaintenanceModeAnswers503WithRetryAfter(): void
    {
        $flag = static::getContainer()->getParameter('app.maintenance_flag_path');
        file_put_contents($flag, '');
        try {
            $this->client->request('GET', '/');

            self::assertResponseStatusCodeSame(503);
            self::assertResponseHeaderSame('Retry-After', '3600');
        } finally {
            @unlink($flag);
        }
    }
}

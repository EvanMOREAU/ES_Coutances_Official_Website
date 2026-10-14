<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\PublicPageCacheListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class PublicPageCacheListenerTest extends TestCase
{
    private function handle(string $route, Request $request, ?Response $response = null, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $request->attributes->set('_route', $route);
        $response ??= new Response('ok');
        (new PublicPageCacheListener())(new ResponseEvent($this->createStub(KernelInterface::class), $request, $type, $response));

        return $response;
    }

    private function withSession(Request $request, bool $started = false): Request
    {
        $session = new Session(new MockArraySessionStorage());
        if ($started) {
            $session->set('panier', [1 => 2]);
        }
        $request->setSession($session);

        return $request;
    }

    public function testAnonymousVisitorGetsAPublicSharedCachePage(): void
    {
        $response = $this->handle('app_legal_mentions', $this->withSession(Request::create('/mentions-legales')));

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
        self::assertStringContainsString('Cookie', (string) $response->headers->get('Vary'));
        self::assertSame('true', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER), 'Symfony ne doit pas repasser la page en privée.');
    }

    public function testVisitorWithACookieKeepsAPrivatePage(): void
    {
        $request = $this->withSession(Request::create('/mentions-legales', 'GET', [], ['PHPSESSID' => 'abc']));

        $response = $this->handle('app_legal_mentions', $request);

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testStartedSessionKeepsAPrivatePage(): void
    {
        $response = $this->handle('boutique_index', $this->withSession(Request::create('/boutique'), true));

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testResponseSettingACookieStaysPrivate(): void
    {
        $response = new Response('ok');
        $response->headers->setCookie(Cookie::create('x', 'y'));

        $this->handle('app_home', $this->withSession(Request::create('/')), $response);

        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testOtherRoutesMethodsAndStatusesAreUntouched(): void
    {
        self::assertFalse($this->handle('app_contact', $this->withSession(Request::create('/contact')))->headers->hasCacheControlDirective('public'));
        self::assertFalse($this->handle('app_home', $this->withSession(Request::create('/', 'POST')))->headers->hasCacheControlDirective('public'));
        self::assertFalse($this->handle('app_home', $this->withSession(Request::create('/')), new Response('x', 404))->headers->hasCacheControlDirective('public'));
        self::assertFalse($this->handle('app_home', $this->withSession(Request::create('/')), null, HttpKernelInterface::SUB_REQUEST)->headers->hasCacheControlDirective('public'));
    }
}

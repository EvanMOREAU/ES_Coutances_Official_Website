<?php

namespace App\Tests\Unit\Service;

use App\Entity\HelloAssoSettings;
use App\Mail\SecretBox;
use App\Repository\HelloAssoSettingsRepository;
use App\Service\HelloAsso\HelloAssoClient;
use App\Service\HelloAsso\HelloAssoException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HelloAssoClientTest extends TestCase
{
    private SecretBox $box;

    protected function setUp(): void
    {
        $this->box = new SecretBox('test-secret');
    }

    private function settings(bool $actif = true, string $environnement = HelloAssoSettings::ENV_SANDBOX): HelloAssoSettings
    {
        return (new HelloAssoSettings())
            ->setActif($actif)
            ->setEnvironnement($environnement)
            ->setClientId('client-id')
            ->setClientSecretChiffre($this->box->encrypt('client-secret'))
            ->setOrganisationSlug('es-coutances');
    }

    /**
     * @param list<MockResponse>       $responses
     * @param list<array{string, string}> $calls
     */
    private function client(HelloAssoSettings $settings, array $responses, array &$calls = []): HelloAssoClient
    {
        $http = new MockHttpClient(function (string $method, string $url) use (&$responses, &$calls): MockResponse {
            $calls[] = [$method, $url];

            return array_shift($responses) ?? new MockResponse('{}', ['http_code' => 500]);
        });
        $repository = $this->createStub(HelloAssoSettingsRepository::class);
        $repository->method('getSingleton')->willReturn($settings);

        return new HelloAssoClient($http, $repository, $this->box, new ArrayAdapter());
    }

    private function token(): MockResponse
    {
        return new MockResponse('{"access_token":"jeton","expires_in":1800}');
    }

    public function testPaymentIntentIsCreatedAgainstTheSandbox(): void
    {
        $calls = [];
        $client = $this->client($this->settings(), [$this->token(), new MockResponse('{"id":42,"redirectUrl":"https://pay.example/42"}')], $calls);

        $intent = $client->creerIntentionPaiement(3500, 'Commande ESC-1', 'https://site/retour', 'https://site/erreur', ['email' => 'a@b.fr', 'firstName' => '']);

        self::assertSame(['id' => 42, 'redirectUrl' => 'https://pay.example/42'], $intent);
        self::assertSame(['POST', 'https://api.helloasso-sandbox.com/oauth2/token'], $calls[0]);
        self::assertSame(['POST', 'https://api.helloasso-sandbox.com/v5/organizations/es-coutances/checkout-intents'], $calls[1]);
    }

    public function testProductionUsesTheProductionHost(): void
    {
        $calls = [];
        $client = $this->client($this->settings(true, HelloAssoSettings::ENV_PRODUCTION), [$this->token(), new MockResponse('{"id":1,"redirectUrl":"u"}')], $calls);

        $client->creerIntentionPaiement(100, 'x', 'b', 'e');

        self::assertStringStartsWith('https://api.helloasso.com/', $calls[0][1]);
    }

    public function testTokenIsCachedBetweenCalls(): void
    {
        $calls = [];
        $client = $this->client($this->settings(), [$this->token(), new MockResponse('{"id":1,"redirectUrl":"u"}'), new MockResponse('{"id":2,"redirectUrl":"v"}')], $calls);

        $client->creerIntentionPaiement(100, 'x', 'b', 'e');
        $client->creerIntentionPaiement(200, 'y', 'b', 'e');

        self::assertCount(1, array_filter($calls, static fn (array $c) => str_ends_with($c[1], '/oauth2/token')), 'Un seul échange de jeton pour deux appels.');
    }

    public function testNotConfiguredIsRefused(): void
    {
        $this->expectException(HelloAssoException::class);
        $this->expectExceptionMessageMatches('/pas configuré/');

        $this->client($this->settings(false), [])->creerIntentionPaiement(100, 'x', 'b', 'e');
    }

    public function testRejectedCredentialsGiveAReadableError(): void
    {
        $client = $this->client($this->settings(), [new MockResponse('{"message":"invalid_client"}', ['http_code' => 401])]);

        $this->expectException(HelloAssoException::class);

        $client->creerIntentionPaiement(100, 'x', 'b', 'e');
    }

    public function testUnexpectedIntentResponseIsAnError(): void
    {
        $client = $this->client($this->settings(), [$this->token(), new MockResponse('{"foo":"bar"}')]);

        $this->expectException(HelloAssoException::class);
        $this->expectExceptionMessage('Réponse HelloAsso inattendue');

        $client->creerIntentionPaiement(100, 'x', 'b', 'e');
    }

    public function testUnreadableSecretAsksToEnterItAgain(): void
    {
        $settings = $this->settings()->setClientSecretChiffre((new SecretBox('un-autre-secret'))->encrypt('x'));

        $this->expectException(HelloAssoException::class);
        $this->expectExceptionMessageMatches('/illisible/');

        $this->client($settings, [])->recupererIntention(1);
    }

    /** @param array<string, mixed> $body */
    private function stateFor(array $body): string
    {
        $client = $this->client($this->settings(), [$this->token(), new MockResponse(json_encode($body) ?: '{}')]);

        return $client->recupererIntention(7)['state'];
    }

    public function testPaymentStateIsDerivedFromTheOrderPayments(): void
    {
        self::assertSame('Waiting', $this->stateFor(['id' => 7]), 'Aucun paiement : en attente.');
        self::assertSame('Authorized', $this->stateFor(['order' => ['payments' => [['state' => 'Refused'], ['state' => 'Authorized']]]]));
        self::assertSame('Processing', $this->stateFor(['order' => ['payments' => [['state' => 'Pending']]]]));
        self::assertSame('Refused', $this->stateFor(['order' => ['payments' => [['state' => 'Refused']]]]));
    }
}

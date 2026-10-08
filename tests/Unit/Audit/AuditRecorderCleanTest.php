<?php

namespace App\Tests\Unit\Audit;

use App\Audit\AuditRecorder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuditRecorderCleanTest extends TestCase
{
    private AuditRecorder $recorder;

    protected function setUp(): void
    {
        $this->recorder = new AuditRecorder($this->createStub(Connection::class), new RequestStack(), $this->createStub(Security::class), new NullLogger());
    }

    #[DataProvider('secretKeys')]
    public function testSecretsAreMasked(string $key): void
    {
        self::assertSame('••••••', $this->recorder->clean('valeur-secrete', $key));
    }

    /** @return iterable<string, array{string}> */
    public static function secretKeys(): iterable
    {
        foreach (['password', 'plainPassword', '_csrf_token', 'clientSecret', 'totpSecret', 'Authorization', 'cookie', 'iban', 'cardNumber', 'cvv'] as $key) {
            yield $key => [$key];
        }
    }

    public function testNestedSecretsAreMasked(): void
    {
        $clean = $this->recorder->clean(['user' => ['email' => 'a@b.fr', 'password' => 'hunter2'], 'liste' => [['token' => 'abc']]]);

        self::assertSame('a@b.fr', $clean['user']['email']);
        self::assertSame('••••••', $clean['user']['password']);
        self::assertSame('••••••', $clean['liste'][0]['token']);
    }

    public function testValuesAreFlattenedAndTruncated(): void
    {
        self::assertSame('2026-10-07 12:30:00', $this->recorder->clean(new \DateTimeImmutable('2026-10-07 12:30:00')));
        self::assertSame('stdClass', $this->recorder->clean(new \stdClass()));
        self::assertSame(1001, mb_strlen((string) $this->recorder->clean(str_repeat('a', 5000))), '1000 caractères + « … »');
        self::assertSame(42, $this->recorder->clean(42));
    }
}

<?php

namespace App\Tests\Unit\Service;

use App\Service\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('amounts')]
    public function testFormat(int $centimes, string $expected): void
    {
        self::assertSame($expected, Money::format($centimes));
    }

    /** @return iterable<string, array{int, string}> */
    public static function amounts(): iterable
    {
        yield 'zéro' => [0, "0,00\u{00A0}€"];
        yield 'centimes' => [5, "0,05\u{00A0}€"];
        yield 'euros' => [2999, "29,99\u{00A0}€"];
        yield 'milliers' => [123456789, "1\u{202F}234\u{202F}567,89\u{00A0}€"];
    }
}

<?php

namespace App\Tests\Unit\Twig;

use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AppExtensionTest extends TestCase
{
    /** @param array<string, mixed> $context */
    private function render(string $template, array $context = []): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]));
        $twig->addExtension(new AppExtension());

        return $twig->render('t', $context);
    }

    public function testFrenchDateFilter(): void
    {
        $date = new \DateTimeImmutable('2026-02-20');

        self::assertSame('20 février 2026', (new AppExtension())->formatDate($date));
        self::assertSame('20/02/2026', (new AppExtension())->formatDate($date, 'dd/MM/y'));
        self::assertSame('', (new AppExtension())->formatDate(null));
    }

    public function testDateFilterInATemplate(): void
    {
        $out = $this->render('{{ d|date_fr }}', ['d' => new \DateTimeImmutable('2026-12-01')]);

        self::assertSame('1 décembre 2026', $out);
    }

    public function testMoneyFilterInATemplate(): void
    {
        self::assertSame("29,99\u{00A0}€", $this->render('{{ 2999|money }}'));
    }

    public function testFunctionsAreRegistered(): void
    {
        $names = array_map(static fn ($f) => $f->getName(), (new AppExtension())->getFunctions());

        self::assertContains('match_live', $names);
        self::assertContains('boutique_en_maintenance', $names);
    }
}

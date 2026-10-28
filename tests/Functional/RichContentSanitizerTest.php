<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Le contenu riche (éditeur Trix) est affiché avec |safe_html : liste blanche stricte, plus aucun |raw direct.
 */
final class RichContentSanitizerTest extends KernelTestCase
{
    private function render(string $html): string
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate('{{ h|safe_html }}')->render(['h' => $html]);
    }

    public function testKeepsEditorFormatting(): void
    {
        $out = $this->render('<div><strong>Gras</strong> <em>italique</em><br><a href="https://example.org/x" title="t">lien</a></div><ul><li>a</li></ul>');

        self::assertStringContainsString('<strong>Gras</strong>', $out);
        self::assertStringContainsString('<a href="https://example.org/x" title="t">lien</a>', $out);
        self::assertStringContainsString('<ul><li>a</li></ul>', $out);
    }

    public function testKeepsUploadedImages(): void
    {
        $out = $this->render('<figure class="attachment"><img src="/uploads/contenu/a.jpg" alt="x" width="10"></figure>');

        self::assertStringContainsString('<img src="/uploads/contenu/a.jpg"', $out);
    }

    public function testStripsScriptsAndEventHandlers(): void
    {
        $out = $this->render('<p onclick="alert(1)" style="color:red">Texte</p><script>alert(1)</script><img src="x" onerror="alert(2)"><iframe src="https://evil.test"></iframe>');

        self::assertStringContainsString('Texte', $out);
        self::assertStringNotContainsString('<script', $out);
        self::assertStringNotContainsString('onclick', $out);
        self::assertStringNotContainsString('onerror', $out);
        self::assertStringNotContainsString('style=', $out);
        self::assertStringNotContainsString('<iframe', $out);
    }

    public function testRejectsDangerousLinkSchemes(): void
    {
        $out = $this->render('<a href="javascript:alert(1)">x</a><img src="data:text/html;base64,AAAA"><form action="https://evil.test"><input name="p"></form>');

        self::assertStringNotContainsString('javascript:', $out);
        self::assertStringNotContainsString('data:', $out);
        self::assertStringNotContainsString('<form', $out);
        self::assertStringNotContainsString('<input', $out);
    }

    public function testSlideTitleIsEscaped(): void
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');
        $out  = $twig->createTemplate("{{ t|e|replace({(m|e): '<span>' ~ m|e ~ '</span>'})|raw }}")
            ->render(['t' => 'Allez <img src=x onerror=alert(1)> les verts', 'm' => 'verts']);

        self::assertStringNotContainsString('<img', $out);
        self::assertStringContainsString('<span>verts</span>', $out);
    }
}

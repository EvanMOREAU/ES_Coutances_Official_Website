<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

/** Chaque clé de traduction utilisée dans un gabarit existe dans translations/messages.fr.yaml. */
final class TranslationsTest extends KernelTestCase
{
    public function testEveryKeyUsedInTemplatesIsDefined(): void
    {
        $translator = static::getContainer()->get('translator');
        $catalogue = $translator->getCatalogue('fr');

        $missing = [];
        $found   = 0;
        foreach ((new Finder())->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig') as $file) {
            if (preg_match_all('/[\'"]([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)[\'"]\s*\|\s*trans\b/', $file->getContents(), $matches)) {
                foreach ($matches[1] as $key) {
                    ++$found;
                    if (!$catalogue->has($key)) {
                        $missing[] = $file->getRelativePathname().' : '.$key;
                    }
                }
            }
        }

        self::assertGreaterThan(0, $found, 'Aucune clé de traduction trouvée dans les gabarits.');
        self::assertSame([], $missing, "Clés absentes de translations/messages.fr.yaml :\n".implode("\n", $missing));
    }

    /** Une phrase française utilisée telle quelle dans un gabarit (« {{ 'Se connecter'|trans }} ») a sa traduction anglaise et allemande. */
    public function testEveryFrenchSentenceUsedAsKeyInTemplatesIsTranslated(): void
    {
        $translator = static::getContainer()->get('translator');
        $missing    = [];
        $found      = 0;
        foreach ((new Finder())->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig') as $file) {
            if (!preg_match_all("/\\{\\{\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\|\\s*trans\\b/", $file->getContents(), $matches)) {
                continue;
            }
            foreach ($matches[1] as $sentence) {
                $sentence = stripslashes($sentence);
                if (1 === preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $sentence)) {
                    continue; // clé « zone.element » : vérifiée par l'autre test
                }
                ++$found;
                foreach (['en', 'de'] as $locale) {
                    if (!$translator->getCatalogue($locale)->has($sentence)) {
                        $missing[] = $locale.' · '.$file->getRelativePathname().' : '.$sentence;
                    }
                }
            }
        }

        self::assertGreaterThan(100, $found);
        self::assertSame([], $missing, "Phrases sans traduction :\n".implode("\n", $missing));
    }

    public function testCartLabelIsPluralized(): void
    {
        $translator = static::getContainer()->get('translator');

        self::assertSame('Mon panier (1 article)', $translator->trans('nav.cart_aria', ['%count%' => 1]));
        self::assertSame('Mon panier (3 articles)', $translator->trans('nav.cart_aria', ['%count%' => 3]));
        self::assertStringContainsString('© '.date('Y').' Entente', $translator->trans('footer.rights', ['%year%' => date('Y')]));
    }
}

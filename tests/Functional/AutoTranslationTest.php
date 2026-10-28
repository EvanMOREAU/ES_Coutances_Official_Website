<?php

namespace App\Tests\Functional;

use App\Entity\PageContenu;
use App\Service\Locale\LocaleRegistry;
use App\Tests\Support\DatabaseTestCase;
use App\Translation\ContentTranslator;
use App\Translation\HtmlTranslator;
use App\Translation\TranslationProvider;
use App\Translation\TranslationStore;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Traduction automatique : enregistrée à la création du contenu, relue en base (jamais de nouvel appel à l'affichage). */
final class AutoTranslationTest extends DatabaseTestCase
{
    private function translator(?FakeTranslationProvider $provider = null): ContentTranslator
    {
        return new ContentTranslator(
            $provider ?? new FakeTranslationProvider(),
            static::getContainer()->get(TranslationStore::class),
            static::getContainer()->get(LocaleRegistry::class),
            $this->em,
            new NullLogger(),
        );
    }

    public function testTranslationIsStoredOnceAndReadFromTheDatabase(): void
    {
        $provider   = new FakeTranslationProvider();
        $translator = $this->translator($provider);
        $store      = static::getContainer()->get(TranslationStore::class);

        self::assertGreaterThan(0, $translator->translateTexts(['Rejoignez-nous'], false, ['en', 'ar']));
        self::assertSame('[en] Rejoignez-nous', $store->get('Rejoignez-nous', 'en'));
        self::assertSame('[ar] Rejoignez-nous', $store->get('Rejoignez-nous', 'ar'));
        self::assertNull($store->get('Rejoignez-nous', 'de'));

        $calls = $provider->calls;
        self::assertSame(0, $translator->translateTexts(['Rejoignez-nous'], false, ['en', 'ar']), 'Un texte déjà traduit n\'est pas renvoyé au service.');
        self::assertSame($calls, $provider->calls);
    }

    public function testEntityFieldsAreTranslatedWithTheirHtml(): void
    {
        $page = (new PageContenu())->setTitre('Notre histoire')->setContenu('<p>Depuis 1961</p>');
        $this->translator()->translateEntities([$page], ['en']);

        $store = static::getContainer()->get(TranslationStore::class);
        self::assertSame('[en] Notre histoire', $store->get('Notre histoire', 'en'));
        self::assertSame('[en] <p>Depuis 1961</p>', $store->get('<p>Depuis 1961</p>', 'en'));
    }

    public function testSiteTextsMissingFromTheCatalogueUseTheStoredTranslation(): void
    {
        $this->translator()->translateTexts(['Se connecter'], false, ['tr']);
        $translator = static::getContainer()->get(TranslationStore::class);
        self::assertSame('[tr] Se connecter', $translator->get('Se connecter', 'tr'));

        $ui = static::getContainer()->get(TranslatorInterface::class);
        self::assertSame('[tr] Se connecter', $ui->trans('Se connecter', [], null, 'tr'));
        self::assertSame('Se connecter', $ui->trans('Se connecter', [], null, 'fr'));
    }

    public function testHardCodedTemplateTextsAreTranslatedInTheRenderedPage(): void
    {
        $store = static::getContainer()->get(TranslationStore::class);
        $store->put('Nos compétitions', 'es', 'Nuestras competiciones');
        $store->put('Rejoignez-nous', 'es', 'Únete a nosotros');
        $store->put('Entente', 'es', 'Acuerdo');

        $html = '<title>Accueil</title><div title="Rejoignez-nous"><h2>  Nos compétitions </h2><span>Entente</span><script>var a = "Nos compétitions";</script></div>';
        $out  = (new HtmlTranslator($store))->translate($html, 'es');

        self::assertStringContainsString('<h2>  Nuestras competiciones </h2>', $out);
        self::assertStringContainsString('title="Únete a nosotros"', $out);
        self::assertStringContainsString('<span>Entente</span>', $out, "Le nom du club n'est jamais traduit.");
        self::assertStringContainsString('var a = "Nos compétitions";', $out, 'Les scripts ne sont pas touchés.');
        self::assertContains('Nos compétitions', (new HtmlTranslator($store))->collect($html));
    }

    public function testPlaceholdersSurviveTheRoundTrip(): void
    {
        $xml = ContentTranslator::protect('Bonjour %name% & bienvenue');
        self::assertSame('Bonjour <x>%name%</x> &amp; bienvenue', $xml);
        self::assertSame('Bonjour %name% & bienvenue', ContentTranslator::restore($xml));
    }
}

final class FakeTranslationProvider implements TranslationProvider
{
    public int $calls = 0;

    public function isConfigured(): bool
    {
        return true;
    }

    public function translate(array $texts, string $locale, bool $html): array
    {
        ++$this->calls;

        return array_map(static fn (string $t): string => '['.$locale.'] '.$t, $texts);
    }
}

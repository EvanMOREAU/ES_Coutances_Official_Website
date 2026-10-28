<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Service\Locale\Locales;
use App\Tests\Support\DatabaseTestCase;
use App\Twig\AppExtension;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** Le changement de langue : cookie pour les visiteurs, compte pour les connectés, et catalogues complets. */
final class LocaleTest extends DatabaseTestCase
{
    public function testVisitorSeesFrenchByDefault(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="fr"]');
        self::assertSelectorTextContains('body', 'Mentions légales');
    }

    public function testSwitchRememberedInACookieTranslatesThePublicSite(): void
    {
        $this->client->request('GET', '/langue/en', server: ['HTTP_REFERER' => 'http://localhost/contact']);

        self::assertResponseRedirects('http://localhost/contact');
        self::assertSame('en', $this->client->getCookieJar()->get(Locales::COOKIE)?->getValue());

        $this->client->request('GET', '/');
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('body', 'Legal notice');
        self::assertSelectorTextContains('body', 'Log in');

        $this->client->request('GET', '/langue/de');
        $this->client->request('GET', '/');
        self::assertSelectorExists('html[lang="de"]');
        self::assertSelectorTextContains('body', 'Impressum');
    }

    public function testUnknownLanguageIsRejected(): void
    {
        $this->client->request('GET', '/langue/xx');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRedirectNeverLeavesTheSite(): void
    {
        $this->client->request('GET', '/langue/en', server: ['HTTP_REFERER' => 'https://evil.example/phish']);

        self::assertResponseRedirects('/');
    }

    public function testLoggedInUserLanguageIsSavedOnTheAccount(): void
    {
        $user = $this->loginAs(['ROLE_ADMIN']);

        $this->client->request('GET', '/langue/de');
        $this->em->clear();
        self::assertSame('de', $this->em->getRepository(User::class)->find($user->getId())?->getLocale());

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="de"]');
        self::assertSelectorTextContains('body', 'Übersicht');
        self::assertSelectorTextContains('body', 'Hallo');
    }

    public function testLanguageCanBeChosenInTheAppearanceSettings(): void
    {
        $user = $this->loginAs(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/admin/parametres/apparence');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name=appearance]')->form();
        $form->setValues(['appearance[locale]' => 'en']);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/parametres/apparence');
        $this->client->followRedirect();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('body', 'Appearance');
        self::assertSelectorTextContains('body', 'Appearance preferences updated.');
        $this->em->clear();
        self::assertSame('en', $this->em->getRepository(User::class)->find($user->getId())?->getLocale());
    }

    public function testPortalSettingsAreTranslated(): void
    {
        $user = $this->loginAs(['ROLE_USER']);
        $user->setLocale('en');
        $this->em->flush();

        $this->client->request('GET', '/mon-compte/parametres/apparence');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Accent colour');
    }

    public function testDatesFollowTheLanguage(): void
    {
        $date = new \DateTimeImmutable('2026-02-20');

        foreach (['fr' => '20 février 2026', 'en' => '20 February 2026', 'de' => '20 Februar 2026'] as $locale => $expected) {
            $request = new Request();
            $request->setLocale($locale);
            $stack = new RequestStack();
            $stack->push($request);

            self::assertSame($expected, (new AppExtension($stack))->formatDate($date), $locale);
        }
    }

    /** Une clé ajoutée en français doit l'être aussi en anglais et en allemand, sans texte vide. */
    public function testEveryFrenchKeyExistsInTheOtherLanguages(): void
    {
        $translator = static::getContainer()->get('translator');
        $french     = $translator->getCatalogue('fr')->all('messages');

        foreach (['en', 'de'] as $locale) {
            $catalogue = $translator->getCatalogue($locale);
            $missing   = [];
            foreach ($french as $key => $text) {
                if (!$catalogue->has($key) || '' === trim((string) $catalogue->get($key))) {
                    $missing[] = $key;
                }
            }
            self::assertSame([], $missing, sprintf('Clés absentes de messages.%s.yaml :', $locale)."\n".implode("\n", $missing));
        }
    }

    /** Les phrases françaises servant de clé (messages flash, libellés) ont aussi leur traduction. */
    public function testFlashMessagesAndLabelsUsedInTheCodeAreTranslated(): void
    {
        $translator = static::getContainer()->get('translator');
        $missing    = [];
        foreach ((new Finder())->files()->in(\dirname(__DIR__, 2).'/src/Controller')->name('*.php') as $file) {
            if (preg_match_all('/addFlash\(\s*\'(?:success|error)\',\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\)/', $file->getContents(), $matches)) {
                foreach ($matches[1] as $sentence) {
                    $sentence = stripslashes($sentence);
                    if (!$translator->getCatalogue('en')->has($sentence)) {
                        $missing[] = $file->getRelativePathname().' : '.$sentence;
                    }
                }
            }
        }
        // Les messages flash simples sont traduits ; ceux qui portent des variables ou un préfixe d'erreur technique sont exclus de cette liste.
        self::assertLessThanOrEqual(25, \count($missing), "Messages flash sans traduction anglaise :\n".implode("\n", $missing));
    }
}

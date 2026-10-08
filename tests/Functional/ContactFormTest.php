<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Mime\Email;

final class ContactFormTest extends DatabaseTestCase
{
    /** @param array<string, string> $values */
    private function submit(array $values): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $form = $crawler->filter('form[name=contact]')->form();
        foreach ($values as $field => $value) {
            $form['contact['.$field.']'] = $value;
        }
        $this->client->submit($form);
    }

    /** @return array<string, string> */
    private function valid(): array
    {
        return [
            'nom' => 'Sophie Leroy', 'email' => 'sophie@test.local', 'telephone' => '0600000000', 'type' => 'inscription',
            'sujet' => 'Inscription U9', 'message' => 'Bonjour, je souhaite inscrire mon fils en U9.',
        ];
    }

    public function testValidMessageIsEmailedToTheClub(): void
    {
        $this->submit($this->valid());

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailHeaderSame($email, 'reply-to', 'sophie@test.local');
        self::assertEmailHeaderSame($email, 'subject', '[Contact ESC] Inscription U9');
        self::assertEmailHtmlBodyContains($email, 'Bonjour, je souhaite inscrire mon fils en U9.');
    }

    public function testUserInputIsEscapedInTheEmail(): void
    {
        $this->submit(['nom' => '<script>alert(1)</script>', 'message' => 'Un message <b>gras</b> assez long.'] + $this->valid());

        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        $html = (string) $message->getHtmlBody();
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<b>gras</b>', $html);
    }

    public function testInvalidMessagesAreNotSent(): void
    {
        foreach ([['email' => 'pas-un-email'], ['nom' => ''], ['message' => 'court'], ['sujet' => 'ab']] as $override) {
            $this->submit($override + $this->valid());
            self::assertEmailCount(0);
        }
    }

    public function testHeaderInjectionIsNotPossible(): void
    {
        $this->submit(['email' => "victime@test.local\r\nBcc: spam@test.local"] + $this->valid());

        self::assertEmailCount(0);
    }
}

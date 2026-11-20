<?php

namespace App\Tests\Functional;

use App\Entity\ContactMessage;
use App\Repository\ContactMessageRepository;
use App\Tests\Support\DatabaseTestCase;

/** Les messages du formulaire de contact sont conservés en base et consultables dans l'admin. */
final class ContactMessageTest extends DatabaseTestCase
{
    private function repo(): ContactMessageRepository
    {
        return static::getContainer()->get(ContactMessageRepository::class);
    }

    /** @param array<string, string> $override */
    private function submit(array $override = []): void
    {
        $values = $override + [
            'nom' => 'Sophie Leroy', 'email' => 'sophie@test.local', 'telephone' => '06 00 00 00 00', 'type' => 'inscription',
            'sujet' => 'Inscription U9', 'message' => 'Bonjour, je souhaite inscrire mon fils en U9.',
        ];
        $crawler = $this->client->request('GET', '/contact');
        $form    = $crawler->filter('form[name=contact]')->form();
        foreach ($values as $field => $value) {
            $form['contact['.$field.']'] = $value;
        }
        $this->client->submit($form);
    }

    private function newMessage(string $sujet = 'Question'): ContactMessage
    {
        $message = (new ContactMessage())->setNom('Paul Martin')->setEmail('paul@test.local')->setType('partenariat')
            ->setSujet($sujet)->setMessage("Bonjour,\nje voudrais devenir partenaire du club.");
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    public function testValidMessageIsStored(): void
    {
        $this->submit();

        self::assertResponseRedirects("/contact");
        $stored = $this->repo()->findAll();
        self::assertCount(1, $stored);
        self::assertSame('Sophie Leroy', $stored[0]->getNom());
        self::assertSame('sophie@test.local', $stored[0]->getEmail());
        self::assertSame('Inscription U9', $stored[0]->getSujet());
        self::assertSame('Inscription / Licence', $stored[0]->getTypeLabel());
        self::assertFalse($stored[0]->isRead());
        self::assertFalse($stored[0]->isArchived());
    }

    public function testInvalidOrSpamMessagesAreNotStored(): void
    {
        $this->submit(['email' => 'pas-un-email']);
        $this->submit(['message' => 'court']);
        $this->submit(['siteWeb' => 'http://spam.example']);

        self::assertCount(0, $this->repo()->findAll());
    }

    public function testAdminCanReadArchiveAndDeleteMessages(): void
    {
        $this->loginAs(['ROLE_ADMIN']);
        $message = $this->newMessage('Devenir partenaire');
        $id      = $message->getId();

        $this->client->request('GET', '/admin/messages-contact');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Devenir partenaire');
        self::assertSame(1, $this->repo()->countUnread());

        // Ouvrir le message le marque comme lu et affiche son contenu complet.
        $this->client->request('GET', '/admin/messages-contact/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'je voudrais devenir partenaire du club');
        $this->em->clear();
        self::assertSame(0, $this->repo()->countUnread());

        // Archiver, puis supprimer (avec un jeton CSRF valide).
        $crawler = $this->client->request('GET', '/admin/messages-contact/'.$id);
        $this->client->submit($crawler->selectButton('Archiver')->form());
        $this->em->clear();
        self::assertTrue($this->repo()->find($id)?->isArchived());

        $crawler = $this->client->request('GET', '/admin/messages-contact/'.$id);
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        $this->em->clear();
        self::assertNull($this->repo()->find($id));
    }

    public function testMessagesAreNotAccessibleWithoutLogin(): void
    {
        $message = $this->newMessage();

        $this->client->request('GET', '/admin/messages-contact');
        self::assertResponseRedirects();
        $this->client->request('GET', '/admin/messages-contact/'.$message->getId());
        self::assertResponseRedirects();
    }
}

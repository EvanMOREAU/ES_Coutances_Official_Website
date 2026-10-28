<?php

namespace App\Tests\Functional;

use App\Entity\Conversation;
use App\Entity\User;
use App\Service\Chat\ChatService;
use App\Tests\Support\DatabaseTestCase;

/** Interrogation régulière de la messagerie : une réponse allégée tant que rien n'a changé. */
final class ChatSyncTest extends DatabaseTestCase
{
    /** @return array<string, mixed> */
    private function sync(string $query = ''): array
    {
        $this->client->request('GET', '/admin/messagerie/api/sync'.$query);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testUnchangedStateIsAnsweredWithoutRebuildingTheConversations(): void
    {
        $alice = $this->loginAsDev();
        $bob   = $this->createUser(['ROLE_DEV']);
        $chat  = static::getContainer()->get(ChatService::class);
        $conversation = $chat->start($alice, [$bob], null, 'Bonjour');

        $first = $this->sync();
        self::assertArrayHasKey('conversations', $first);
        self::assertNotEmpty($first['v']);
        self::assertSame($conversation->getId(), $first['conversations'][0]['id']);

        // Même empreinte : rien de nouveau.
        $again = $this->sync('?v='.$first['v']);
        self::assertTrue($again['unchanged']);
        self::assertArrayNotHasKey('conversations', $again);

        // Un message arrive : l'empreinte change, la réponse complète revient.
        // Les entités ont été détachées par les requêtes précédentes.
        $chat->send($this->em->find(Conversation::class, $conversation->getId()), $this->em->find(User::class, $bob->getId()), 'Salut');
        $updated = $this->sync('?v='.$first['v']);
        self::assertArrayNotHasKey('unchanged', $updated);
        self::assertSame(1, $updated['unread']);
        self::assertNotSame($first['v'], $updated['v']);

        // Une empreinte inventée ne passe jamais pour « inchangé ».
        self::assertArrayHasKey('conversations', $this->sync('?v=0000000000000000'));
    }

    public function testStampDependsOnTheOpenedConversation(): void
    {
        $alice = $this->loginAsDev();
        $bob   = $this->createUser(['ROLE_DEV']);
        $chat  = static::getContainer()->get(ChatService::class);
        $conversation = $chat->start($alice, [$bob], null, 'Bonjour');

        $closed = $this->sync();
        $opened = $this->sync('?active='.$conversation->getId());

        self::assertNotSame($closed['v'], $opened['v']);
        self::assertNotNull($opened['active']);
        // Ouvrir une discussion ne peut pas être masqué par l'empreinte de la liste seule.
        self::assertArrayHasKey('conversations', $this->sync('?active='.$conversation->getId().'&v='.$closed['v']));
    }
}

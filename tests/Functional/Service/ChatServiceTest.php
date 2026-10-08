<?php

namespace App\Tests\Functional\Service;

use App\Entity\Message;
use App\Entity\User;
use App\Service\Chat\ChatException;
use App\Service\Chat\ChatService;
use App\Tests\Support\DatabaseTestCase;

final class ChatServiceTest extends DatabaseTestCase
{
    private ChatService $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chat = static::getContainer()->get(ChatService::class);
    }

    private function staff(): User
    {
        return $this->createUser(['ROLE_DEV']);
    }

    public function testStaffCanStartAConversationAndExchangeMessages(): void
    {
        [$alice, $bob] = [$this->staff(), $this->staff()];

        $conversation = $this->chat->start($alice, [$bob], null, 'Bonjour Bob');
        $this->chat->send($conversation, $bob, 'Salut Alice');

        self::assertCount(2, $this->em->getRepository(Message::class)->findBy(['conversation' => $conversation]));
        self::assertTrue($this->chat->canAccess($conversation, $alice));
        self::assertTrue($this->chat->canAccess($conversation, $bob));
    }

    public function testStartingTwiceWithTheSamePersonReusesTheConversation(): void
    {
        [$alice, $bob] = [$this->staff(), $this->staff()];

        $first = $this->chat->start($alice, [$bob]);
        $second = $this->chat->start($bob, [$alice]);

        self::assertSame($first->getId(), $second->getId());
    }

    public function testOutsidersCannotReadOrWrite(): void
    {
        [$alice, $bob, $eve] = [$this->staff(), $this->staff(), $this->staff()];
        $conversation = $this->chat->start($alice, [$bob], null, 'Privé');

        self::assertFalse($this->chat->canAccess($conversation, $eve));

        $this->expectException(ChatException::class);
        $this->chat->send($conversation, $eve, 'Je m\'incruste');
    }

    public function testEmptyAndOversizedMessagesAreRefused(): void
    {
        [$alice, $bob] = [$this->staff(), $this->staff()];
        $conversation = $this->chat->start($alice, [$bob]);

        foreach (['', "  \n ", str_repeat('a', Message::MAX_LENGTH + 1)] as $body) {
            try {
                $this->chat->send($conversation, $alice, $body);
                self::fail('Un message vide ou trop long doit être refusé.');
            } catch (ChatException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMessagesAreNormalizedAndStoredAsText(): void
    {
        [$alice, $bob] = [$this->staff(), $this->staff()];
        $conversation = $this->chat->start($alice, [$bob]);

        $message = $this->chat->send($conversation, $alice, "  ligne 1\r\nligne 2 <script>alert(1)</script>  ");

        self::assertSame("ligne 1\nligne 2 <script>alert(1)</script>", $message->getBody());
    }

    public function testConversationWithYourselfOrNobodyIsRefused(): void
    {
        $alice = $this->staff();

        $this->expectException(ChatException::class);
        $this->chat->start($alice, [$alice]);
    }

    public function testMessageSerializationFlagsAuthorship(): void
    {
        [$alice, $bob] = [$this->staff(), $this->staff()];
        $conversation = $this->chat->start($alice, [$bob]);
        $message = $this->chat->send($conversation, $alice, 'Hello');

        self::assertTrue($this->chat->serialize($message, $alice)['mine']);
        self::assertFalse($this->chat->serialize($message, $bob)['mine']);
    }

    public function testClientNeverSeesTheNameOfTheSupportAgent(): void
    {
        $client = $this->createUser(['ROLE_FAMILLE']);
        $agent = $this->staff()->setNom('Secret')->setPrenom('Agent');
        $this->em->flush();
        $support = $this->chat->supportConversationFor($client);
        $reply = $this->chat->send($support, $agent, 'Bonjour');

        self::assertSame('Support', $this->chat->serialize($reply, $client)['authorName']);
        self::assertSame('Agent Secret', $this->chat->serialize($reply, $agent)['authorName']);
    }

    public function testReadStateIsPerParticipant(): void
    {
        [$alice, $bob] = [$this->staff(), $this->staff()];
        $conversation = $this->chat->start($alice, [$bob]);
        $message = $this->chat->send($conversation, $alice, 'Tu as lu ?');

        self::assertSame(0, $this->chat->readUpTo($conversation, $alice), 'Bob n\'a encore rien lu.');

        $this->chat->markRead($conversation, $bob, (int) $message->getId());

        self::assertSame((int) $message->getId(), $this->chat->readUpTo($conversation, $alice));
    }

    public function testClientsOnlyTalkToTheSupport(): void
    {
        $client = $this->createUser(['ROLE_FAMILLE']); // sans famille ni licencié rattaché : simple client de la boutique
        $staff = $this->staff();

        self::assertTrue($this->chat->isClient($client));
        self::assertFalse($this->chat->isClient($staff));

        $this->expectException(ChatException::class);
        $this->chat->start($client, [$staff], null, 'Je peux vous écrire ?');
    }

    public function testSupportConversationIsCreatedOnceAndAnsweredByAuthorisedStaff(): void
    {
        $client = $this->createUser(['ROLE_FAMILLE']);
        $agent = $this->staff();

        $support = $this->chat->supportConversationFor($client);
        self::assertSame($support->getId(), $this->chat->supportConversationFor($client)->getId());
        self::assertTrue($support->isSupport());
        self::assertTrue($this->chat->canAccess($support, $agent), 'Un membre habilité répond au support sans en être participant.');

        $reply = $this->chat->send($support, $agent, 'Bonjour, comment puis-je vous aider ?');
        self::assertSame('Bonjour, comment puis-je vous aider ?', $reply->getBody());
        self::assertTrue($this->chat->canAccess($support, $client));
    }

    public function testEditorWithoutSupportPermissionCannotAnswerSupport(): void
    {
        $client = $this->createUser(['ROLE_FAMILLE']);
        $editor = $this->createUser(['ROLE_EDITOR']);
        $support = $this->chat->supportConversationFor($client);

        self::assertFalse($this->chat->canAccess($support, $editor));
        $this->expectException(ChatException::class);
        $this->chat->send($support, $editor, 'Coucou');
    }

    public function testGroupSizeIsLimited(): void
    {
        $me = $this->staff();
        $others = [];
        for ($i = 0; $i < 25; ++$i) {
            $others[] = $this->staff();
        }

        $this->expectException(ChatException::class);
        $this->expectExceptionMessageMatches('/limitée/');
        $this->chat->start($me, $others, 'Trop grand');
    }
}

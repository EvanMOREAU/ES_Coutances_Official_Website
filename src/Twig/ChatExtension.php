<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\Chat\ChatService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Compteur de messages non lus affiché à côté du lien « Messagerie ». */
class ChatExtension extends AbstractExtension
{
    private ?int $cached = null;

    public function __construct(
        private readonly ChatService $chat,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('chat_unread', $this->unread(...))];
    }

    public function unread(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->cached ??= $this->chat->unreadTotal($user);
    }
}

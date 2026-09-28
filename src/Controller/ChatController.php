<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User;
use App\Service\Chat\ChatException;
use App\Service\Chat\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messagerie : la même page et la même API servent l'admin (/admin/messagerie,
 * pour les gérants) et l'espace familles / licenciés (/mon-compte/messagerie).
 * Les deux zones ont chacune leur connexion (pare-feu distincts), d'où deux séries de routes.
 *
 * « En direct » : le navigateur interroge /api/sync toutes les 2-3 secondes et ne
 * reçoit que ce qui est nouveau (aucun serveur temps réel à installer).
 */
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/messagerie', name: 'admin_chat_index', methods: ['GET'])]
    #[Route('/mon-compte/messagerie', name: 'portail_chat_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $isAdmin = str_starts_with((string) $request->attributes->get('_route'), 'admin_');
        $me      = $this->me();

        return $this->render($isAdmin ? 'admin/chat/index.html.twig' : 'portail/messagerie.html.twig', [
            'chat_base'   => $this->generateUrl($isAdmin ? 'admin_chat_index' : 'portail_chat_index'),
            'chat_staff'  => $me->isStaff(),
            'chat_open'   => $request->query->getInt('c'),
        ]);
    }

    /**
     * Point d'interrogation régulier : liste des discussions (avec non-lus) et,
     * pour la discussion ouverte, les nouveaux messages depuis « after ».
     */
    #[Route('/admin/messagerie/api/sync', name: 'admin_chat_sync', methods: ['GET'])]
    #[Route('/mon-compte/messagerie/api/sync', name: 'portail_chat_sync', methods: ['GET'])]
    public function sync(Request $request): JsonResponse
    {
        $me = $this->me();
        $this->chat->touchPresence($me);

        $payload = ['active' => null];
        $activeId = $request->query->getInt('active');
        if ($activeId > 0) {
            $conversation = $this->em->find(Conversation::class, $activeId);
            if ($conversation !== null && $conversation->participantFor($me) !== null) {
                $after  = max(0, $request->query->getInt('after'));
                $thread = $this->chat->thread($conversation, $me, after: $after);
                $seen   = $after;
                foreach ($thread['messages'] as $message) {
                    $seen = max($seen, $message['id']);
                }
                // On ne marque « lu » que ce que le navigateur reçoit dans cette réponse.
                $this->chat->markRead($conversation, $me, $seen);

                $payload['active'] = ['id' => $activeId] + $thread;
            }
        }

        $summaries = $this->chat->summaries($me);
        $payload['conversations'] = $summaries;
        $payload['unread']        = array_sum(array_column($summaries, 'unread'));

        return $this->json($payload, headers: ['Cache-Control' => 'no-store']);
    }

    /** Ouverture d'une discussion : ses derniers messages (ou une page plus ancienne avec « before »). */
    #[Route('/admin/messagerie/api/conversations/{id}', name: 'admin_chat_thread', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/mon-compte/messagerie/api/conversations/{id}', name: 'portail_chat_thread', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function thread(Request $request, int $id): JsonResponse
    {
        $me           = $this->me();
        $conversation = $this->conversationFor($id, $me);
        $before       = $request->query->has('before') ? $request->query->getInt('before') : null;

        $thread = $this->chat->thread($conversation, $me, before: $before);
        if ($before === null) {
            $latest = 0;
            foreach ($thread['messages'] as $message) {
                $latest = max($latest, $message['id']);
            }
            $this->chat->markRead($conversation, $me, $latest);
        }

        return $this->json($thread, headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/admin/messagerie/api/conversations/{id}/messages', name: 'admin_chat_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Route('/mon-compte/messagerie/api/conversations/{id}/messages', name: 'portail_chat_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(Request $request, int $id): JsonResponse
    {
        return $this->guarded($request, function () use ($request, $id): array {
            $me           = $this->me();
            $conversation = $this->conversationFor($id, $me);
            $message      = $this->chat->send($conversation, $me, (string) ($this->payload($request)['body'] ?? ''));

            return ['message' => $this->chat->serialize($message, $me, $this->chat->readUpTo($conversation, $me))];
        });
    }

    #[Route('/admin/messagerie/api/conversations', name: 'admin_chat_start', methods: ['POST'])]
    #[Route('/mon-compte/messagerie/api/conversations', name: 'portail_chat_start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        return $this->guarded($request, function () use ($request): array {
            $me   = $this->me();
            $data = $this->payload($request);

            $ids   = array_values(array_unique(array_filter(array_map('intval', (array) ($data['participants'] ?? [])))));
            $users = $ids === [] ? [] : $this->em->getRepository(User::class)->findBy(['id' => $ids]);
            if (\count($users) !== \count($ids)) {
                throw new ChatException('Destinataire introuvable.');
            }
            $conversation = $this->chat->start($me, $users, (string) ($data['subject'] ?? ''), (string) ($data['message'] ?? ''));

            return ['id' => $conversation->getId()];
        });
    }

    #[Route('/admin/messagerie/api/contacts', name: 'admin_chat_contacts', methods: ['GET'])]
    #[Route('/mon-compte/messagerie/api/contacts', name: 'portail_chat_contacts', methods: ['GET'])]
    public function contacts(): JsonResponse
    {
        return $this->json(['contacts' => $this->chat->contacts($this->me())], headers: ['Cache-Control' => 'no-store']);
    }

    // -- Utilitaires -----------------------------------------------------------

    private function me(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /** Une discussion n'existe, pour un utilisateur, que s'il en fait partie. */
    private function conversationFor(int $id, User $me): Conversation
    {
        $conversation = $this->em->find(Conversation::class, $id);
        if ($conversation === null || $conversation->participantFor($me) === null) {
            throw $this->createNotFoundException();
        }

        return $conversation;
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    /**
     * @param callable(): array<string, mixed> $callback
     */
    private function guarded(Request $request, callable $callback): JsonResponse
    {
        if (!$this->isCsrfTokenValid('chat', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Session expirée : rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        try {
            return $this->json(['ok' => true] + $callback());
        } catch (ChatException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

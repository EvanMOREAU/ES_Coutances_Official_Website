<?php

namespace App\Controller\Admin;

use App\Service\AdminNotificationProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Centre de notifications : page complète + actions (marquer comme lue,
 * supprimer) appelées en AJAX depuis la clochette et depuis la page.
 */
#[Route('/admin/notifications')]
class NotificationController extends AbstractController
{
    private const TOKEN_ID = 'admin-notifications';

    #[Route('', name: 'admin_notification_index', methods: ['GET'])]
    public function index(AdminNotificationProvider $provider): Response
    {
        return $this->render('admin/notifications/index.html.twig', [
            'notifications' => $provider->getNotifications(),
        ]);
    }

    #[Route('/lire', name: 'admin_notification_read', methods: ['POST'])]
    public function read(Request $request, AdminNotificationProvider $provider): JsonResponse
    {
        return $this->act($request, $provider, $provider->markRead(...));
    }

    #[Route('/supprimer', name: 'admin_notification_dismiss', methods: ['POST'])]
    public function dismiss(Request $request, AdminNotificationProvider $provider): JsonResponse
    {
        return $this->act($request, $provider, $provider->dismiss(...));
    }

    /** @param callable(list<string>|null): void $action */
    private function act(Request $request, AdminNotificationProvider $provider, callable $action): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::TOKEN_ID, (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $request->getPayload();
        $keys    = $payload->getBoolean('all')
            ? null
            : array_values(array_filter(array_map('strval', $payload->all('keys')), static fn (string $k) => '' !== $k));

        if (null !== $keys && [] === $keys) {
            return new JsonResponse(['error' => 'Aucune notification indiquée.'], Response::HTTP_BAD_REQUEST);
        }

        $action($keys);

        return new JsonResponse([
            'unread' => $provider->unreadCount(),
            'total'  => count($provider->getNotifications()),
        ]);
    }
}

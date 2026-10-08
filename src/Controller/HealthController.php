<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point de contrôle pour la supervision (UptimeRobot, load balancer…) : répond 200 si le site
 * et sa base de données fonctionnent, 503 sinon. N'expose aucune information interne.
 */
final class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET', 'HEAD'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return $this->noStore(new JsonResponse(['status' => 'error'], JsonResponse::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->noStore(new JsonResponse(['status' => 'ok']));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}

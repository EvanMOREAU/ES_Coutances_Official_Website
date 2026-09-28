<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mémorise, par utilisateur, l'affichage de chaque tableau de l'admin
 * (colonnes masquées, nombre de lignes par page) pour le retrouver à la
 * prochaine connexion.
 */
#[IsGranted('ROLE_USER')]
class TablePreferencesController extends AbstractController
{
    private const PER_PAGE_CHOICES = [10, 25, 50, 100];

    #[Route('/admin/preferences/tableaux/{key}', name: 'admin_table_preferences', requirements: ['key' => '[a-z_]{1,40}'], methods: ['POST'])]
    public function save(string $key, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin-table-preferences', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['ok' => false], 403);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['ok' => false], 400);
        }

        $hidden = array_values(array_unique(array_filter(
            (array) ($payload['hidden'] ?? []),
            static fn ($column) => is_string($column) && 1 === preg_match('/^[a-z_]{1,40}$/', $column),
        )));

        $perPage = (int) ($payload['perPage'] ?? 10);
        if (!in_array($perPage, self::PER_PAGE_CHOICES, true)) {
            $perPage = 10;
        }

        $preferences = $user->getTablePreferences();
        $preferences[$key] = ['hidden' => $hidden, 'perPage' => $perPage];
        $user->setTablePreferences($preferences);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}

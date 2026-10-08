<?php

namespace App\Controller\Admin;

use App\Entity\Deployment;
use App\Entity\User;
use App\Repository\DeploymentRepository;
use App\Service\Deploy\DeployService;
use App\Service\Deploy\UpdateWatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Mise à jour du site depuis le dépôt GitHub (menu « Mise à jour »). */
#[Route('/admin/mise-a-jour')]
class DeployController extends AbstractController
{
    #[Route('', name: 'admin_deploy_index', methods: ['GET'])]
    public function index(Request $request, DeployService $deploy, DeploymentRepository $deployments): Response
    {
        $page   = max(1, $request->query->getInt('page', 1));
        $result = $deployments->paginated($page);

        return $this->render('admin/deploy/index.html.twig', [
            'status'      => $deploy->status(),
            'running'     => $deploy->running(),
            'history'     => $result['rows'],
            'page'        => $page,
            'pages'       => max(1, (int) ceil($result['total'] / DeploymentRepository::PER_PAGE)),
            'total'       => $result['total'],
        ]);
    }

    /**
     * Nombre de mises à jour disponibles, interrogé en arrière-plan par toutes les pages du back-office pour
     * afficher la pastille du menu. Déclenche (au plus toutes les 10 minutes) la vérification auprès de GitHub.
     */
    #[Route('/etat', name: 'admin_deploy_pending', methods: ['GET'])]
    public function pending(UpdateWatcher $watcher, DeployService $deploy): JsonResponse
    {
        $watcher->refreshIfStale();

        return $this->json(
            ['pending' => $watcher->pendingCount(), 'running' => null !== $deploy->running()],
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    /** Interroge GitHub (git fetch) et renvoie les commits pas encore déployés. */
    #[Route('/verifier', name: 'admin_deploy_check', methods: ['POST'])]
    public function check(Request $request, DeployService $deploy): JsonResponse
    {
        $this->denyUnlessCsrf($request);

        $status = $deploy->status(fetch: true);

        return $this->json([
            'error'   => $status['error'],
            'dirty'   => $status['dirty'],
            'branch'  => $status['branch'],
            'pending' => array_map(static fn (array $c) => [
                'short'   => $c['short'],
                'subject' => $c['subject'],
                'author'  => $c['author'],
                'date'    => $c['date']->format('d/m/Y H:i'),
            ], $status['pending']),
        ]);
    }

    #[Route('/lancer', name: 'admin_deploy_start', methods: ['POST'])]
    public function start(Request $request, DeployService $deploy): JsonResponse
    {
        $this->denyUnlessCsrf($request);

        $user = $this->getUser();
        try {
            $deployment = $deploy->start($user instanceof User ? $user : null);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['id' => $deployment->getId()]);
    }

    #[Route('/{id}/statut', name: 'admin_deploy_status', methods: ['GET'])]
    public function progress(Deployment $deployment): JsonResponse
    {
        return $this->json([
            'id'       => $deployment->getId(),
            'status'   => $deployment->getStatus(),
            'step'     => $deployment->getStep(),
            'log'      => $deployment->getLog(),
            'to'       => $deployment->getToCommit() ? substr($deployment->getToCommit(), 0, 7) : null,
        ]);
    }

    /** Supprime l'archive des fichiers d'une mise à jour, après validation de l'utilisateur (la sauvegarde de la base est conservée). */
    #[Route('/{id}/sauvegarde/supprimer', name: 'admin_deploy_backup_purge', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function purgeBackup(Deployment $deployment, Request $request, DeployService $deploy): JsonResponse
    {
        $this->denyUnlessCsrf($request);

        try {
            $deploy->purgeFilesBackup($deployment);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['ok' => true]);
    }

    private function denyUnlessCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('deploy', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}

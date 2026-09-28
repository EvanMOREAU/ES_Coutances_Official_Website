<?php

namespace App\Controller\Admin;

use App\Entity\Deployment;
use App\Entity\User;
use App\Repository\DeploymentRepository;
use App\Service\Deploy\DeployService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Mise à jour du site depuis le dépôt GitHub (réservée aux comptes développeur). */
#[Route('/admin/deploiement')]
#[IsGranted('ROLE_DEV')]
class DeployController extends AbstractController
{
    #[Route('', name: 'admin_deploy_index', methods: ['GET'])]
    public function index(DeployService $deploy, DeploymentRepository $deployments): Response
    {
        return $this->render('admin/deploy/index.html.twig', [
            'status'      => $deploy->status(),
            'running'     => $deploy->running(),
            'history'     => $deployments->findRecent(10),
        ]);
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

    private function denyUnlessCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('deploy', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}

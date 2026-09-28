<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EntrainementRepository;
use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Planning en lecture seule pour les familles et licenciés : seules les séances
 * des catégories de leurs licenciés actifs sont visibles.
 */
#[Route('/mon-compte/planning')]
class PortailPlanningController extends AbstractController
{
    public function __construct(
        private readonly EntrainementRepository $repository,
        private readonly PlanningService $planning,
    ) {
    }

    #[Route('', name: 'portail_planning', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('portail/planning.html.twig', [
            'categories' => $this->planning->categoriesFor($this->currentUser()),
        ]);
    }

    #[Route('/evenements', name: 'portail_planning_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $licencies = $this->planning->licenciesFor($this->currentUser());
        if ([] === $licencies) {
            return new JsonResponse(['events' => []]);
        }

        // Filtre facultatif par catégorie, limité aux catégories des licenciés du compte.
        $categorie = (string) $request->query->get('categorie', '');
        if ('' !== $categorie) {
            $licencies = array_values(array_filter($licencies, static fn ($l) => $l->getCategorie() === $categorie));
        }

        if ($request->query->has('prochains')) {
            $today  = new \DateTimeImmutable('today');
            $from   = $today;
            $to     = $today->modify('+1 year');
            $limit  = max(1, min(20, $request->query->getInt('prochains', 5)));
        } else {
            $from = $this->planning->parseDate($request->query->get('start'));
            $to   = $this->planning->parseDate($request->query->get('end'));
            $limit = null;
            if (!$from || !$to || $to < $from || $from->diff($to)->days > 62) {
                return new JsonResponse(['error' => 'Période invalide.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $result = array_values(array_filter(
            $this->repository->between($from, $to),
            fn ($e) => $this->planning->concerns($e, $licencies),
        ));
        if (null !== $limit) {
            $result = array_slice($result, 0, $limit);
        }

        return new JsonResponse(['events' => array_map($this->planning->serialize(...), $result)]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}

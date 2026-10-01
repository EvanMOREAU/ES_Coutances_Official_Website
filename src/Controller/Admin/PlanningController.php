<?php

namespace App\Controller\Admin;

use App\Entity\Entrainement;
use App\Entity\User;
use App\Repository\EntrainementRepository;
use App\Service\CategorieAge;
use App\Service\PlanningService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Planning des entraînements par catégorie. La page est un calendrier mensuel
 * (planning_controller.js) qui lit et écrit les séances via les routes JSON ci-dessous.
 */
#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    private const TOKEN_ID = 'admin-planning';

    public function __construct(
        private readonly EntrainementRepository $repository,
        private readonly PlanningService $planning,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_planning_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/planning/index.html.twig', [
            'categories'   => array_values(CategorieAge::choices()),
            'equipes'      => $this->planning->equipeChoices(),
            'profils'      => $this->planning->profilChoices(),
            'utilisateurs' => $this->planning->userChoices(),
        ]);
    }

    #[Route('/evenements', name: 'admin_planning_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $categorie = (string) $request->query->get('categorie', '');
        $filter    = '' !== $categorie ? [$categorie] : null;

        if ($request->query->has('prochains')) {
            $today  = new \DateTimeImmutable('today');
            $result = $this->repository->between($today, $today->modify('+1 year'), $filter, max(1, min(20, $request->query->getInt('prochains', 5))));
        } else {
            $start = $this->planning->parseDate($request->query->get('start'));
            $end   = $this->planning->parseDate($request->query->get('end'));
            if (!$start || !$end || $end < $start || $start->diff($end)->days > 62) {
                return new JsonResponse(['error' => 'Période invalide.'], Response::HTTP_BAD_REQUEST);
            }
            $result = $this->repository->between($start, $end, $filter);
        }

        $user   = $this->getUser();
        $result = array_values(array_filter($result, fn ($e) => !$user instanceof User || $this->planning->visibleEvenement($e, $user)));

        return new JsonResponse(['events' => array_map($this->planning->serialize(...), $result)]);
    }

    #[Route('/evenements', name: 'admin_planning_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ($denied = $this->checkToken($request)) {
            return $denied;
        }

        ['errors' => $errors, 'values' => $v] = $this->planning->validate($request->getPayload()->all());
        if ($errors) {
            return $this->invalid($errors);
        }

        $serie = $v['jusqua'] ? bin2hex(random_bytes(16)) : null;
        $date  = $v['date'];
        $count = 0;
        do {
            $e = (new Entrainement())->setDate($date)->setSerie($serie);
            $this->fill($e, $v);
            $this->em->persist($e);
            ++$count;
            $date = $date->modify('+7 days');
        } while ($v['jusqua'] && $date <= $v['jusqua']);

        $this->em->flush();

        return new JsonResponse(['ok' => true, 'count' => $count], Response::HTTP_CREATED);
    }

    #[Route('/evenements/{id}', name: 'admin_planning_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(Request $request, Entrainement $entrainement): JsonResponse
    {
        if ($denied = $this->checkToken($request)) {
            return $denied;
        }

        $payload = $request->getPayload();
        ['errors' => $errors, 'values' => $v] = $this->planning->validate(array_merge($payload->all(), ['repeter' => false]));
        if ($errors) {
            return $this->invalid($errors);
        }

        $targets = [$entrainement];
        if ($entrainement->getSerie() && 'serie' === $payload->getString('portee')) {
            $targets = $this->repository->inSerieFrom($entrainement->getSerie(), $entrainement->getDate());
        }

        // Si la date change, toutes les séances concernées sont décalées d'autant.
        $shift = $entrainement->getDate()->diff($v['date']);
        foreach ($targets as $e) {
            $e->setDate($e->getDate()->add($shift));
            $this->fill($e, $v);
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'count' => count($targets)]);
    }

    #[Route('/evenements/{id}/supprimer', name: 'admin_planning_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Entrainement $entrainement): JsonResponse
    {
        if ($denied = $this->checkToken($request)) {
            return $denied;
        }

        $targets = [$entrainement];
        if ($entrainement->getSerie() && 'serie' === $request->getPayload()->getString('portee')) {
            $targets = $this->repository->inSerieFrom($entrainement->getSerie(), $entrainement->getDate());
        }
        foreach ($targets as $e) {
            $this->em->remove($e);
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'count' => count($targets)]);
    }

    /** @param array<string, mixed> $v valeurs validées par PlanningService::validate() */
    private function fill(Entrainement $e, array $v): void
    {
        $e->setType($v['type'])
            ->setEquipes($v['equipes'])
            ->setTitre($v['titre'])
            ->setCategories($v['categories'])
            ->setHeureDebut($v['debut'])
            ->setHeureFin($v['fin'])
            ->setLieu($v['lieu'])
            ->setDescription($v['description'])
            ->setPartageProfils($v['partageProfils'])
            ->setPartageRoles($v['partageRoles'])
            ->setPartageUtilisateurs($v['partageUtilisateurs']);
    }

    private function checkToken(Request $request): ?JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::TOKEN_ID, (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Session expirée : rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): JsonResponse
    {
        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

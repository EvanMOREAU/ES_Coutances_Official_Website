<?php

namespace App\Controller\Admin;

use App\Entity\Partenaire;
use App\Form\PartenaireType;
use App\Repository\PartenaireRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/partenaires')]
class PartenaireController extends AbstractController
{
    #[Route('', name: 'admin_partenaire_index', methods: ['GET'])]
    public function index(PartenaireRepository $repository): Response
    {
        return $this->render('admin/partenaire/index.html.twig', [
            'partenaires' => $repository->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_partenaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $partenaire = new Partenaire();
        $partenaire->setOrdre($ordreService->getNextOrdre(Partenaire::class));

        $form = $this->createForm(PartenaireType::class, $partenaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($partenaire);
            $em->flush();

            $this->addFlash('success', 'Partenaire créé.');

            return $this->redirectToRoute('admin_partenaire_index');
        }

        return $this->render('admin/partenaire/form.html.twig', [
            'form'       => $form,
            'partenaire' => $partenaire,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_partenaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Partenaire $partenaire, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PartenaireType::class, $partenaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Partenaire mis à jour.');

            return $this->redirectToRoute('admin_partenaire_index');
        }

        return $this->render('admin/partenaire/form.html.twig', [
            'form'       => $form,
            'partenaire' => $partenaire,
        ]);
    }

    #[Route('/reordonner', name: 'admin_partenaire_reorder', methods: ['POST'])]
    public function reorder(Request $request, PartenaireRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('reorder-partenaire', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Jeton invalide.'], 403);
        }

        $partenaires = $repository->findBy(['id' => $data['ids'] ?? []]);
        $byId        = [];
        foreach ($partenaires as $partenaire) {
            $byId[$partenaire->getId()] = $partenaire;
        }

        foreach (array_values($data['ids'] ?? []) as $index => $id) {
            $byId[$id]?->setOrdre($index);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/supprimer', name: 'admin_partenaire_delete', methods: ['POST'])]
    public function delete(Request $request, Partenaire $partenaire, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-partenaire-'.$partenaire->getId(), $request->request->get('_token'))) {
            $em->remove($partenaire);
            $em->flush();
            $this->addFlash('success', 'Partenaire supprimé.');
        }

        return $this->redirectToRoute('admin_partenaire_index');
    }
}

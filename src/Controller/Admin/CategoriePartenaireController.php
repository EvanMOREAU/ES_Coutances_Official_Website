<?php

namespace App\Controller\Admin;

use App\Entity\CategoriePartenaire;
use App\Form\CategoriePartenaireType;
use App\Repository\CategoriePartenaireRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Catégories (rôles) de partenaire : gold, argent, fournisseur… Définies par un développeur. */
#[Route('/admin/categories-partenaires')]
class CategoriePartenaireController extends AbstractController
{
    #[Route('', name: 'admin_categorie_partenaire_index', methods: ['GET'])]
    public function index(CategoriePartenaireRepository $repository): Response
    {
        return $this->render('admin/categorie_partenaire/index.html.twig', [
            'categories' => $repository->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_categorie_partenaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $categorie = new CategoriePartenaire();
        $categorie->setOrdre($ordreService->getNextOrdre(CategoriePartenaire::class));

        $form = $this->createForm(CategoriePartenaireType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();

            $this->addFlash('success', 'Catégorie de partenaire créée.');

            return $this->redirectToRoute('admin_categorie_partenaire_index');
        }

        return $this->render('admin/categorie_partenaire/form.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_categorie_partenaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategoriePartenaire $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategoriePartenaireType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('admin_categorie_partenaire_index');
        }

        return $this->render('admin/categorie_partenaire/form.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/reordonner', name: 'admin_categorie_partenaire_reorder', methods: ['POST'])]
    public function reorder(Request $request, CategoriePartenaireRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('reorder-categorie-partenaire', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Jeton invalide.'], 403);
        }

        $categories = $repository->findBy(['id' => $data['ids'] ?? []]);
        $byId       = [];
        foreach ($categories as $categorie) {
            $byId[$categorie->getId()] = $categorie;
        }

        foreach (array_values($data['ids'] ?? []) as $index => $id) {
            $byId[$id]?->setOrdre($index);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/supprimer', name: 'admin_categorie_partenaire_delete', methods: ['POST'])]
    public function delete(Request $request, CategoriePartenaire $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-categorie-partenaire-'.$categorie->getId(), $request->request->get('_token'))) {
            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_categorie_partenaire_index');
    }
}

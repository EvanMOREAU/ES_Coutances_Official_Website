<?php

namespace App\Controller\Admin;

use App\Entity\CategorieArticle;
use App\Form\CategorieArticleType;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Catégories de la boutique (T-shirts, Joggings…), utilisées pour trier les articles. */
#[Route('/admin/boutique/categories')]
class CategorieArticleController extends AbstractController
{
    #[Route('', name: 'admin_boutique_categorie_index', methods: ['GET'])]
    public function index(CategorieArticleRepository $repository): Response
    {
        return $this->render('admin/boutique/categorie_article_index.html.twig', [
            'categories' => $repository->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_boutique_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CategorieArticleRepository $repository): Response
    {
        $categorie = new CategorieArticle();

        $form = $this->createForm(CategorieArticleType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categorie->setSlug($repository->uniqueSlug($categorie));
            $em->persist($categorie);
            $em->flush();

            $this->addFlash('success', sprintf('Catégorie "%s" créée avec succès.', $categorie->getNom()));

            return $this->redirectToRoute('admin_boutique_categorie_index');
        }

        return $this->render('admin/boutique/categorie_article_form.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_boutique_categorie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieArticle $categorie, EntityManagerInterface $em, CategorieArticleRepository $repository): Response
    {
        $form = $this->createForm(CategorieArticleType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categorie->setSlug($repository->uniqueSlug($categorie));
            $em->flush();

            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('admin_boutique_categorie_index');
        }

        return $this->render('admin/boutique/categorie_article_form.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_boutique_categorie_delete', methods: ['POST'])]
    public function delete(Request $request, CategorieArticle $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-boutique-categorie-' . $categorie->getId(), (string) $request->request->get('_token'))) {
            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_boutique_categorie_index');
    }
}

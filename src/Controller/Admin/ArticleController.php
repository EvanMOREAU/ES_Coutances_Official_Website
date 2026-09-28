<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\ArticleVariante;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Articles de la boutique : fiche, photo, prix et tailles avec leur stock. */
#[Route('/admin/boutique/articles')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'admin_article_index', methods: ['GET'])]
    public function index(ArticleRepository $repository): Response
    {
        return $this->render('admin/boutique/article_index.html.twig', [
            'articles' => $repository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    /** Fiche de l'article en lecture seule, avant de la modifier. */
    #[Route('/{id}', name: 'admin_article_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Article $article): Response
    {
        return $this->render('admin/boutique/article_show.html.twig', ['article' => $article]);
    }

    #[Route('/nouveau', name: 'admin_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ArticleRepository $repository, CategorieArticleRepository $categories): Response
    {
        $article = new Article();
        $form    = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($repository->uniqueSlug($article));
            $article->setCategorie($categories->findOrCreateByName($form->get('categorieNom')->getData()));
            $this->orderVariantes($article);
            $em->persist($article);
            $em->flush();
            $this->addFlash('success', 'Article créé.');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/boutique/article_form.html.twig', [
            'form'       => $form,
            'article'    => $article,
            'categories' => $categories->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_article_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $em, CategorieArticleRepository $categories): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setCategorie($categories->findOrCreateByName($form->get('categorieNom')->getData()));
            $this->orderVariantes($article);
            $em->flush();
            $this->addFlash('success', 'Article mis à jour.');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/boutique/article_form.html.twig', [
            'form'       => $form,
            'article'    => $article,
            'categories' => $categories->findBy([], ['id' => 'ASC']),
        ]);
    }

    /** Réassort : ajoute (additionne) une quantité au stock de chaque taille de l'article. */
    #[Route('/{id}/stock', name: 'admin_article_stock', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function stock(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('stock-article-' . $article->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, veuillez réessayer.');

                return $this->redirectToRoute('admin_article_index');
            }

            $quantites = $request->request->all('quantite');
            $ajoute    = false;
            foreach ($article->getVariantes() as $variante) {
                $quantite = max(0, (int) ($quantites[$variante->getId()] ?? 0));
                if ($quantite > 0) {
                    $variante->setStock($variante->getStock() + $quantite);
                    $ajoute = true;
                }
            }

            if ($ajoute) {
                $em->flush();
                $this->addFlash('success', 'Stock mis à jour.');
            }

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/boutique/_stock_modal.html.twig', ['article' => $article]);
    }

    #[Route('/{id}/supprimer', name: 'admin_article_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-article-' . $article->getId(), (string) $request->request->get('_token'))) {
            // Les commandes déjà passées gardent leur détail (nom, taille, prix recopiés).
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Article supprimé.');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    /** L'ordre des tailles est celui du formulaire. */
    private function orderVariantes(Article $article): void
    {
        $position = 0;
        foreach ($article->getVariantes() as $variante) {
            /** @var ArticleVariante $variante */
            $variante->setOrdre($position++);
        }
    }
}

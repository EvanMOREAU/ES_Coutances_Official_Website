<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\ArticleVariante;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/** Articles de la boutique : fiche, photo, prix et tailles avec leur stock. */
#[Route('/admin/boutique/articles')]
class ArticleController extends AbstractController
{
    /** Session : photo déjà envoyée le temps de corriger une erreur (ex. taille manquante) à la création. */
    private const PENDING_IMAGE_SESSION_KEY = 'article_new_pending_image';
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

        if (!$form->isSubmitted()) {
            // Nouvelle page vierge : on oublie une éventuelle photo restée en attente d'une tentative précédente.
            $this->forgetPendingImage($request);
        } elseif (!$article->getImageFile()) {
            // Le champ photo n'a pas été renvoyé (navigateur) : on reprend celle de la tentative précédente, s'il y en a une.
            $this->restorePendingImage($request, $article);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($repository->uniqueSlug($article));
            $article->setCategorie($categories->findOrCreateByName($form->get('categorieNom')->getData()));
            $this->orderVariantes($article);
            $em->persist($article);
            $em->flush();
            $this->forgetPendingImage($request);
            $this->addFlash('success', 'Article créé.');

            return $this->redirectToRoute('admin_article_index');
        }

        // Le formulaire est invalide (ex. taille manquante) mais une photo a été envoyée : on la garde de côté
        // pour que l'utilisateur n'ait pas à la renvoyer après avoir corrigé l'erreur.
        if ($form->isSubmitted() && $article->getImageFile()) {
            $this->rememberPendingImage($request, $article->getImageFile());
        }

        return $this->render('admin/boutique/article_form.html.twig', [
            'form'              => $form,
            'article'           => $article,
            'categories'        => $categories->findBy([], ['id' => 'ASC']),
            'hasPendingImage'   => null !== $article->getImageFile() && null === $article->getImageName(),
        ]);
    }

    /** Déplace la photo envoyée vers un stockage temporaire durable et retient son chemin en session. */
    private function rememberPendingImage(Request $request, File $file): void
    {
        $currentPath = $request->getSession()->get(self::PENDING_IMAGE_SESSION_KEY);
        if (is_string($currentPath) && $currentPath === $file->getPathname()) {
            return; // déjà mise de côté lors d'une tentative précédente : rien à faire
        }

        $dir = $this->getParameter('kernel.project_dir') . '/var/tmp/article-uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        // Une autre photo était en attente (remplacée par ce nouvel envoi) : on l'efface.
        if (is_string($currentPath) && is_file($currentPath)) {
            @unlink($currentPath);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . ($file->guessExtension() ?: 'bin');
        $moved    = $file->move($dir, $filename);

        $request->getSession()->set(self::PENDING_IMAGE_SESSION_KEY, $moved->getPathname());
    }

    /** Réinjecte la photo mise de côté, si elle existe encore. */
    private function restorePendingImage(Request $request, Article $article): void
    {
        $path = $request->getSession()->get(self::PENDING_IMAGE_SESSION_KEY);
        if (is_string($path) && is_file($path)) {
            // Vich n'« uploade » que les instances d'UploadedFile (ou ReplacingFile) : un simple File
            // restauré depuis le stockage temporaire serait ignoré silencieusement.
            $article->setImageFile(new ReplacingFile($path));
        }
    }

    /** Oublie (et supprime) la photo mise de côté, une fois l'article créé ou l'opération abandonnée. */
    private function forgetPendingImage(Request $request): void
    {
        $path = $request->getSession()->get(self::PENDING_IMAGE_SESSION_KEY);
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
        $request->getSession()->remove(self::PENDING_IMAGE_SESSION_KEY);
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

<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\ArticleVarianteRepository;
use App\Repository\CategorieArticleRepository;
use App\Service\Boutique\Panier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Boutique du club (site public) : catalogue et panier. Le tunnel de commande, le suivi et la facture sont dans
 * BoutiqueCommandeController, le paiement par carte (HelloAsso) dans BoutiquePaiementController.
 */
#[Route('/boutique')]
class BoutiqueController extends AbstractController implements BoutiqueAreaInterface
{
    public function __construct(
        private readonly Panier $panier,
    ) {
    }

    // ------------------------------------------------------------------ catalogue

    #[Route('', name: 'boutique_index', methods: ['GET'])]
    public function index(Request $request, ArticleRepository $articles, CategorieArticleRepository $categories): Response
    {
        $categorieSlug = $request->query->get('categorie');

        return $this->render('boutique/index.html.twig', [
            'articles'      => $articles->findVisibles($categorieSlug),
            'categories'    => $categories->findBy([], ['id' => 'ASC']),
            'categorieSlug' => $categorieSlug,
        ]);
    }

    #[Route('/article/{slug}', name: 'boutique_article', methods: ['GET'])]
    public function article(string $slug, ArticleRepository $articles): Response
    {
        $article = $articles->findVisibleBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('boutique/article.html.twig', ['article' => $article]);
    }

    // ------------------------------------------------------------------ panier

    #[Route('/panier', name: 'boutique_panier', methods: ['GET'])]
    public function panier(): Response
    {
        $detail = $this->panier->detail();
        foreach ($detail['messages'] as $message) {
            $this->addFlash('boutique_warning', $message);
        }

        return $this->render('boutique/panier.html.twig', $detail);
    }

    #[Route('/panier/ajouter', name: 'boutique_panier_ajouter', methods: ['POST'])]
    public function ajouter(Request $request, ArticleVarianteRepository $variantes): Response
    {
        $variante = $variantes->find($request->request->getInt('variante'));
        $back     = $variante ? $this->generateUrl('boutique_article', ['slug' => $variante->getArticle()->getSlug()]) : $this->generateUrl('boutique_index');

        if (!$this->isCsrfTokenValid('boutique-panier', (string) $request->request->get('_token'))) {
            $this->addFlash('boutique_error', 'Votre session a expiré, veuillez réessayer.');

            return $this->redirect($back);
        }
        if (!$variante || !$variante->getArticle()->isActif()) {
            $this->addFlash('boutique_error', 'Cet article n\'est plus disponible.');

            return $this->redirectToRoute('boutique_index');
        }
        if ($variante->getStock() <= 0) {
            $this->addFlash('boutique_error', 'Cette taille est épuisée.');

            return $this->redirect($back);
        }

        $souhaitee = max(1, $request->request->getInt('quantite', 1));
        $presente  = $this->panier->add($variante, $souhaitee);
        if ($presente < $souhaitee) {
            $this->addFlash('boutique_warning', sprintf('Quantité limitée à %d pour cet article.', $presente));
        } else {
            $this->addFlash('boutique_success', 'Article ajouté à votre panier.');
        }

        return $this->redirectToRoute('boutique_panier');
    }

    #[Route('/panier/modifier', name: 'boutique_panier_modifier', methods: ['POST'])]
    public function modifier(Request $request, ArticleVarianteRepository $variantes): Response
    {
        if ($this->isCsrfTokenValid('boutique-panier', (string) $request->request->get('_token'))) {
            $variante = $variantes->find($request->request->getInt('variante'));
            if ($variante) {
                $this->panier->set($variante, $request->request->getInt('quantite'));
            }
        }

        return $this->redirectToRoute('boutique_panier');
    }

    #[Route('/panier/retirer', name: 'boutique_panier_retirer', methods: ['POST'])]
    public function retirer(Request $request): Response
    {
        if ($this->isCsrfTokenValid('boutique-panier', (string) $request->request->get('_token'))) {
            $this->panier->remove($request->request->getInt('variante'));
        }

        return $this->redirectToRoute('boutique_panier');
    }
}

<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Form\CheckoutType;
use App\Repository\ArticleRepository;
use App\Repository\ArticleVarianteRepository;
use App\Repository\CategorieArticleRepository;
use App\Repository\CommandeRepository;
use App\Repository\FamilleRepository;
use App\Service\Boutique\CommandeMailer;
use App\Service\Boutique\CommandeService;
use App\Service\Boutique\Panier;
use App\Service\Boutique\StockInsuffisantException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Boutique du club (site public) : catalogue, panier, commande, suivi.
 * Il n'y a pas de livraison : les commandes se retirent au club. Le paiement
 * par carte est pour l'instant simulé (voir paiement()), en attendant HelloAsso.
 */
#[Route('/boutique')]
class BoutiqueController extends AbstractController
{
    public function __construct(
        private readonly Panier $panier,
        private readonly CommandeService $commandes,
        private readonly CommandeMailer $mailer,
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

    // ------------------------------------------------------------------ commande

    #[Route('/commande', name: 'boutique_commande', methods: ['GET', 'POST'])]
    public function commande(Request $request, FamilleRepository $familles): Response
    {
        $detail = $this->panier->detail();
        foreach ($detail['messages'] as $message) {
            $this->addFlash('boutique_warning', $message);
        }
        if ([] === $detail['lignes']) {
            return $this->redirectToRoute('boutique_panier');
        }

        $user = $this->getUser();
        $form = $this->createForm(CheckoutType::class, $user instanceof User ? $this->prefill($user, $familles) : null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quantites = [];
            foreach ($detail['lignes'] as $ligne) {
                $quantites[$ligne['variante']->getId()] = $ligne['quantite'];
            }

            try {
                $commande = $this->commandes->passer($quantites, $form->getData(), $user instanceof User ? $user : null);
            } catch (StockInsuffisantException $e) {
                $this->addFlash('boutique_error', $e->getMessage() . ' Votre panier a été mis à jour.');

                return $this->redirectToRoute('boutique_panier');
            }

            $this->panier->clear();
            $this->mailer->confirmation($commande);
            $this->addFlash('boutique_success', 'Merci ! Votre commande est enregistrée.');

            return Commande::PAIEMENT_CARTE === $commande->getModePaiement()
                ? $this->redirectToRoute('boutique_paiement', $this->routeParams($commande))
                : $this->redirectToRoute('boutique_commande_suivi', $this->routeParams($commande));
        }

        return $this->render('boutique/commande.html.twig', $detail + ['form' => $form]);
    }

    #[Route('/commande/{reference}/{token}', name: 'boutique_commande_suivi', methods: ['GET'])]
    public function suivi(string $reference, string $token, CommandeRepository $commandes): Response
    {
        return $this->render('boutique/suivi.html.twig', ['commande' => $this->findCommande($commandes, $reference, $token)]);
    }

    /**
     * Paiement par carte — SIMULÉ. Cette page tient la place de la page de
     * paiement HelloAsso : « Payer » enregistre le règlement comme si le
     * prestataire l'avait confirmé. À remplacer par la redirection vers
     * HelloAsso et son retour (webhook) le moment venu.
     */
    #[Route('/commande/{reference}/{token}/paiement', name: 'boutique_paiement', methods: ['GET', 'POST'])]
    public function paiement(string $reference, string $token, Request $request, CommandeRepository $commandes): Response
    {
        $commande = $this->findCommande($commandes, $reference, $token);
        $suivi    = $this->redirectToRoute('boutique_commande_suivi', $this->routeParams($commande));

        if (Commande::PAIEMENT_CARTE !== $commande->getModePaiement() || !$commande->isReglementDu()) {
            return $suivi;
        }

        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('boutique-paiement-' . $commande->getId(), (string) $request->request->get('_token'))) {
                $this->commandes->appliquer($commande, 'payer');
                $this->addFlash('boutique_success', 'Paiement reçu, merci !');
            }

            return $suivi;
        }

        return $this->render('boutique/paiement.html.twig', ['commande' => $commande]);
    }

    // ------------------------------------------------------------------ outils

    private function findCommande(CommandeRepository $commandes, string $reference, string $token): Commande
    {
        $commande = $commandes->findOneBy(['reference' => $reference]);
        // Le jeton est comparé en temps constant : la référence seule ne suffit pas à consulter une commande.
        if (!$commande || !hash_equals((string) $commande->getToken(), $token)) {
            throw $this->createNotFoundException();
        }

        return $commande;
    }

    /** @return array{reference: string, token: string} */
    private function routeParams(Commande $commande): array
    {
        return ['reference' => (string) $commande->getReference(), 'token' => (string) $commande->getToken()];
    }

    /** @return array<string, ?string> coordonnées connues du compte connecté */
    private function prefill(User $user, FamilleRepository $familles): array
    {
        return [
            'prenom'    => $user->getPrenom(),
            'nom'       => $user->getNom(),
            'email'     => $user->getEmail(),
            'telephone' => $familles->findOneBy(['user' => $user])?->getTelephone(),
        ];
    }
}

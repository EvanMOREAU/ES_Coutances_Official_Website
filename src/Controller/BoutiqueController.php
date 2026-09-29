<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Form\CheckoutType;
use App\Form\LivraisonType;
use App\Repository\ArticleRepository;
use App\Repository\ArticleVarianteRepository;
use App\Repository\CategorieArticleRepository;
use App\Repository\CodePromoRepository;
use App\Repository\CommandeRepository;
use App\Repository\FamilleRepository;
use App\Service\Boutique\CommandeMailer;
use App\Service\Boutique\CommandeService;
use App\Service\Boutique\Panier;
use App\Service\Boutique\PromoCodeException;
use App\Service\Boutique\StockInsuffisantException;
use App\Service\HelloAsso\HelloAssoClient;
use App\Service\HelloAsso\HelloAssoException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Boutique du club (site public) : catalogue, panier, commande, suivi.
 * Il n'y a pas de livraison par défaut : les commandes se retirent au club. Seul un code de
 * réduction marqué « bon de livraison » (CodePromo::autoriseLivraison) débloque une étape
 * supplémentaire (voir commandeLivraison()) pour renseigner une adresse. Le paiement par
 * carte se fait via HelloAsso (voir paiement()/paiementRetour()).
 */
#[Route('/boutique')]
class BoutiqueController extends AbstractController
{
    /** Le temps de remplir l'étape « livraison », les coordonnées du client patientent en session. */
    private const SESSION_CHECKOUT = 'boutique_checkout_client';

    public function __construct(
        private readonly Panier $panier,
        private readonly CommandeService $commandes,
        private readonly CommandeMailer $mailer,
        private readonly CodePromoRepository $codesPromo,
        private readonly HelloAssoClient $helloAsso,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
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
            $client = $form->getData();

            // Un code « bon de livraison » ouvre une étape en plus pour l'adresse ; sinon on
            // finalise directement (un code invalide sera de toute façon rejeté par passer()).
            $code = trim((string) ($client['codePromo'] ?? ''));
            if ('' !== $code) {
                $codePromo = $this->codesPromo->findParCode($code);
                if ($codePromo && $codePromo->isValide() && $codePromo->isAutoriseLivraison()) {
                    $request->getSession()->set(self::SESSION_CHECKOUT, $client);

                    return $this->redirectToRoute('boutique_commande_livraison');
                }
            }

            try {
                $commande = $this->passerCommande($detail, $client, $user instanceof User ? $user : null);
            } catch (StockInsuffisantException $e) {
                $this->addFlash('boutique_error', $e->getMessage() . ' Votre panier a été mis à jour.');

                return $this->redirectToRoute('boutique_panier');
            } catch (PromoCodeException $e) {
                $this->addFlash('boutique_error', $e->getMessage());

                return $this->render('boutique/commande.html.twig', $detail + ['form' => $form]);
            }

            return $this->commandeReussie($commande);
        }

        return $this->render('boutique/commande.html.twig', $detail + ['form' => $form]);
    }

    /**
     * Aperçu en direct (sans consommer le code) : appelé en AJAX pendant la saisie, à l'étape
     * « Vos informations », pour mettre à jour le total affiché avant même de valider le formulaire.
     */
    #[Route('/code-promo/verifier', name: 'boutique_code_promo_verifier', methods: ['GET'])]
    public function verifierCodePromo(Request $request): JsonResponse
    {
        $sousTotal = $this->panier->detail()['total'];
        $code      = trim((string) $request->query->get('code', ''));

        if ('' === $code) {
            return $this->json(['valide' => false, 'sousTotalCentimes' => $sousTotal, 'totalCentimes' => $sousTotal, 'reductionCentimes' => 0]);
        }

        $codePromo = $this->codesPromo->findParCode($code);
        if (!$codePromo || !$codePromo->isValide()) {
            return $this->json([
                'valide'            => false,
                'message'           => 'Ce code de réduction est invalide ou n\'est plus valable.',
                'sousTotalCentimes' => $sousTotal,
                'totalCentimes'     => $sousTotal,
                'reductionCentimes' => 0,
            ]);
        }

        $reduction = $codePromo->calculerReductionCentimes($sousTotal);

        return $this->json([
            'valide'             => true,
            'sousTotalCentimes'  => $sousTotal,
            'reductionCentimes'  => $reduction,
            'totalCentimes'      => max(0, $sousTotal - $reduction),
            'autoriseLivraison'  => $codePromo->isAutoriseLivraison(),
        ]);
    }

    /**
     * Étape intercalée avant la confirmation, uniquement quand le code saisi à l'étape
     * précédente autorise la livraison : sans un tel code, cette page n'est pas accessible
     * (par défaut, une commande ne peut pas être livrée, seulement retirée au club).
     */
    #[Route('/commande/livraison', name: 'boutique_commande_livraison', methods: ['GET', 'POST'])]
    public function commandeLivraison(Request $request): Response
    {
        $detail  = $this->panier->detail();
        $session = $request->getSession();
        $client  = $session->get(self::SESSION_CHECKOUT);

        if ([] === $detail['lignes'] || !is_array($client)) {
            return $this->redirectToRoute('boutique_commande');
        }

        $codePromo = $this->codesPromo->findParCode((string) ($client['codePromo'] ?? ''));
        if (!$codePromo || !$codePromo->isValide() || !$codePromo->isAutoriseLivraison()) {
            $session->remove(self::SESSION_CHECKOUT);

            return $this->redirectToRoute('boutique_commande');
        }

        $form = $this->createForm(LivraisonType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client = array_merge($client, $form->getData());
            $user   = $this->getUser();

            try {
                $commande = $this->passerCommande($detail, $client, $user instanceof User ? $user : null);
            } catch (StockInsuffisantException $e) {
                $session->remove(self::SESSION_CHECKOUT);
                $this->addFlash('boutique_error', $e->getMessage() . ' Votre panier a été mis à jour.');

                return $this->redirectToRoute('boutique_panier');
            } catch (PromoCodeException $e) {
                $session->remove(self::SESSION_CHECKOUT);
                $this->addFlash('boutique_error', $e->getMessage());

                return $this->redirectToRoute('boutique_commande');
            }

            $session->remove(self::SESSION_CHECKOUT);

            return $this->commandeReussie($commande);
        }

        return $this->render('boutique/commande_livraison.html.twig', $detail + ['form' => $form, 'codePromo' => $codePromo]);
    }

    #[Route('/commande/{reference}/{token}', name: 'boutique_commande_suivi', methods: ['GET'])]
    public function suivi(string $reference, string $token, CommandeRepository $commandes): Response
    {
        return $this->render('boutique/suivi.html.twig', ['commande' => $this->findCommande($commandes, $reference, $token)]);
    }

    /**
     * Redirige le client vers HelloAsso pour régler sa commande par carte : crée une intention
     * de paiement pour le montant exact et renvoie vers la page de paiement hébergée par
     * HelloAsso. Le retour (paiementRetour()) vérifie le résultat auprès de l'API — on ne fait
     * jamais confiance à la seule redirection du navigateur.
     */
    #[Route('/commande/{reference}/{token}/paiement', name: 'boutique_paiement', methods: ['GET'])]
    public function paiement(string $reference, string $token, CommandeRepository $commandes): Response
    {
        $commande = $this->findCommande($commandes, $reference, $token);
        $suivi    = $this->redirectToRoute('boutique_commande_suivi', $this->routeParams($commande));

        if (Commande::PAIEMENT_CARTE !== $commande->getModePaiement() || !$commande->isReglementDu()) {
            return $suivi;
        }

        // Une réduction à 100% laisse un total nul : rien à faire payer, on encaisse directement.
        if ($commande->getTotalCentimes() <= 0) {
            $this->commandes->appliquer($commande, 'payer');
            $this->addFlash('boutique_success', 'Paiement reçu, merci !');

            return $suivi;
        }

        $retourUrl = $this->generateUrl('boutique_paiement_retour', $this->routeParams($commande), UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $intention = $this->helloAsso->creerIntentionPaiement(
                $commande->getTotalCentimes(),
                sprintf('Commande %s', $commande->getReference()),
                $retourUrl,
                $retourUrl,
                [
                    'firstName' => $commande->getPrenom(),
                    'lastName'  => $commande->getNom(),
                    'email'     => $commande->getEmail(),
                ],
            );
        } catch (HelloAssoException $e) {
            $this->logger->error('Échec de création de l\'intention de paiement HelloAsso pour la commande {reference} : {message}', ['reference' => $commande->getReference(), 'message' => $e->getMessage()]);
            $this->addFlash('boutique_error', 'Le paiement en ligne est momentanément indisponible. Contactez le club pour régler autrement.');

            return $suivi;
        }

        $commande->setHelloAssoCheckoutIntentId($intention['id']);
        $this->em->flush();

        return $this->redirect($intention['redirectUrl']);
    }

    /**
     * Retour depuis HelloAsso (succès ou échec) : on revérifie toujours le statut réel auprès de
     * l'API avant de marquer la commande payée, la redirection seule pouvant être rejouée par
     * n'importe qui.
     */
    #[Route('/commande/{reference}/{token}/paiement/retour', name: 'boutique_paiement_retour', methods: ['GET'])]
    public function paiementRetour(string $reference, string $token, CommandeRepository $commandes): Response
    {
        $commande = $this->findCommande($commandes, $reference, $token);
        $suivi    = $this->redirectToRoute('boutique_commande_suivi', $this->routeParams($commande));

        if (Commande::PAIEMENT_CARTE !== $commande->getModePaiement() || !$commande->isReglementDu()) {
            return $suivi;
        }

        $checkoutIntentId = $commande->getHelloAssoCheckoutIntentId();
        if (!$checkoutIntentId) {
            $this->addFlash('boutique_error', 'Impossible de vérifier ce paiement : réessayez depuis cette page.');

            return $suivi;
        }

        try {
            $intention = $this->helloAsso->recupererIntention($checkoutIntentId);
        } catch (HelloAssoException $e) {
            $this->logger->error('Échec de vérification de l\'intention de paiement HelloAsso {id} pour la commande {reference} : {message}', ['id' => $checkoutIntentId, 'reference' => $commande->getReference(), 'message' => $e->getMessage()]);
            $this->addFlash('boutique_error', 'Impossible de vérifier votre paiement pour le moment. Réessayez depuis cette page dans un instant.');

            return $suivi;
        }

        $etat = $intention['state'] ?? null;
        if ('Authorized' === $etat) {
            $this->commandes->appliquer($commande, 'payer');
            $this->addFlash('boutique_success', 'Paiement reçu, merci !');
        } elseif (in_array($etat, ['Waiting', 'Processing'], true)) {
            $this->addFlash('boutique_warning', 'Votre paiement est en cours de traitement : nous vous confirmerons par e-mail dès sa validation.');
        } else {
            $this->addFlash('boutique_error', 'Le paiement n\'a pas abouti. Vous pouvez réessayer depuis cette page.');
        }

        return $suivi;
    }

    // ------------------------------------------------------------------ outils

    /**
     * @param array{lignes: list<array{variante: \App\Entity\ArticleVariante, quantite: int, total: int}>, total: int, messages: list<string>} $detail
     * @param array<string, mixed> $client
     *
     * @throws StockInsuffisantException
     * @throws PromoCodeException
     */
    private function passerCommande(array $detail, array $client, ?User $user): Commande
    {
        $quantites = [];
        foreach ($detail['lignes'] as $ligne) {
            $quantites[$ligne['variante']->getId()] = $ligne['quantite'];
        }

        return $this->commandes->passer($quantites, $client, $user);
    }

    private function commandeReussie(Commande $commande): Response
    {
        $this->panier->clear();
        $this->mailer->confirmation($commande);
        $this->addFlash('boutique_success', 'Merci ! Votre commande est enregistrée.');

        return Commande::PAIEMENT_CARTE === $commande->getModePaiement()
            ? $this->redirectToRoute('boutique_paiement', $this->routeParams($commande))
            : $this->redirectToRoute('boutique_commande_suivi', $this->routeParams($commande));
    }

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

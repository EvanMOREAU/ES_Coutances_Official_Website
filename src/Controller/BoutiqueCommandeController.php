<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesCommande;
use App\Entity\Commande;
use App\Entity\User;
use App\Form\CheckoutType;
use App\Form\LivraisonType;
use App\Repository\CodePromoRepository;
use App\Repository\CommandeRepository;
use App\Repository\ContactSettingsRepository;
use App\Repository\FamilleRepository;
use App\Service\Boutique\CommandeMailer;
use App\Service\Boutique\CommandeService;
use App\Service\Boutique\Panier;
use App\Service\Boutique\PromoCodeException;
use App\Service\Boutique\StockInsuffisantException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Boutique du club : tunnel de commande, suivi et facture. Il n'y a pas de livraison par défaut : les
 * commandes se retirent au club. Seul un bon de livraison (CodePromo) valide débloque une étape
 * supplémentaire (voir commandeLivraison()) pour renseigner une adresse. Le paiement par carte est
 * dans BoutiquePaiementController.
 *
 * @phpstan-type Client array{
 *     prenom: string, nom: string, email: string, telephone: ?string, note: ?string, modePaiement: string,
 *     codePromo?: ?string, cgv?: bool,
 *     livraisonAdresse?: ?string, livraisonComplement?: ?string, livraisonCodePostal?: ?string,
 *     livraisonVille?: ?string, livraisonTelephone?: ?string, livraisonInstructions?: ?string,
 * }
 */
#[Route('/boutique')]
class BoutiqueCommandeController extends AbstractController implements BoutiqueAreaInterface
{
    use ResolvesCommande;

    /** Le temps de remplir l'étape « livraison », les coordonnées du client patientent en session. */
    private const SESSION_CHECKOUT = 'boutique_checkout_client';

    public function __construct(
        private readonly Panier $panier,
        private readonly CommandeService $commandes,
        private readonly CommandeMailer $mailer,
        private readonly CodePromoRepository $codesPromo,
        #[Autowire('%env(LEGAL_ASSOCIATION_ID)%')] private readonly string $associationId,
        #[Autowire('%env(LEGAL_VAT_MENTION)%')] private readonly string $vatMention,
    ) {
    }

    // ------------------------------------------------------------------ commande

    /** Un compte est obligatoire pour commander (catalogue et panier restent accessibles sans compte). */
    #[Route('/commande', name: 'boutique_commande', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
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

            // Un bon de livraison valide ouvre une étape en plus pour l'adresse ; sinon on
            // finalise directement (un code invalide sera de toute façon rejeté par passer()).
            $code = trim((string) ($client['codePromo'] ?? ''));
            if ('' !== $code) {
                $codePromo = $this->codesPromo->findParCode($code);
                if ($codePromo && $codePromo->isValide() && $codePromo->estUtilisablePar($user instanceof User ? $user : null)) {
                    $request->getSession()->set(self::SESSION_CHECKOUT, $client);

                    return $this->redirectToRoute('boutique_commande_livraison');
                }
            }

            try {
                $commande = $this->passerCommande($detail, $client, $user instanceof User ? $user : null, $request->getClientIp());
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
     * Vérification en direct (sans consommer le code) : appelé en AJAX pendant la saisie, à
     * l'étape « Vos informations », pour prévenir le client tout de suite qu'un code valide
     * débloquera une étape de livraison, avant même de valider le formulaire.
     */
    #[Route('/code-promo/verifier', name: 'boutique_code_promo_verifier', methods: ['GET'])]
    public function verifierCodePromo(Request $request): JsonResponse
    {
        $code = trim((string) $request->query->get('code', ''));

        if ('' === $code) {
            return $this->json(['valide' => false]);
        }

        $user      = $this->getUser();
        $codePromo = $this->codesPromo->findParCode($code);
        if (!$codePromo || !$codePromo->isValide() || !$codePromo->estUtilisablePar($user instanceof User ? $user : null)) {
            return $this->json(['valide' => false, 'message' => 'Ce code est invalide ou n\'est plus valable.']);
        }

        return $this->json(['valide' => true]);
    }

    /**
     * Étape intercalée avant la confirmation, uniquement quand le code saisi à l'étape
     * précédente est un bon de livraison valide : sans un tel code, cette page n'est pas
     * accessible (par défaut, une commande ne peut pas être livrée, seulement retirée au club).
     */
    #[Route('/commande/livraison', name: 'boutique_commande_livraison', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function commandeLivraison(Request $request): Response
    {
        $detail  = $this->panier->detail();
        $session = $request->getSession();
        /** @var Client|null $client coordonnées enregistrées en session par l'étape précédente */
        $client  = $session->get(self::SESSION_CHECKOUT);

        if ([] === $detail['lignes'] || !is_array($client)) {
            return $this->redirectToRoute('boutique_commande');
        }

        $user      = $this->getUser();
        $codePromo = $this->codesPromo->findParCode((string) ($client['codePromo'] ?? ''));
        if (!$codePromo || !$codePromo->isValide() || !$codePromo->estUtilisablePar($user instanceof User ? $user : null)) {
            $session->remove(self::SESSION_CHECKOUT);

            return $this->redirectToRoute('boutique_commande');
        }

        $form = $this->createForm(LivraisonType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client = array_merge($client, $form->getData());

            try {
                $commande = $this->passerCommande($detail, $client, $user instanceof User ? $user : null, $request->getClientIp());
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

    /** Facture imprimable (« Enregistrer au format PDF » du navigateur) : n'existe qu'une fois la commande réglée. */
    #[Route('/commande/{reference}/{token}/facture', name: 'boutique_commande_facture', methods: ['GET'])]
    public function facture(string $reference, string $token, CommandeRepository $commandes, ContactSettingsRepository $contact): Response
    {
        $commande = $this->findCommande($commandes, $reference, $token);
        if (null === $commande->getNumeroFacture()) {
            throw $this->createNotFoundException();
        }

        return $this->render('boutique/facture.html.twig', [
            'commande'       => $commande,
            'contact'        => $contact->getSingleton(),
            'association_id' => $this->associationId,
            'vat_mention'    => $this->vatMention,
        ]);
    }

    // ------------------------------------------------------------------ outils

    /**
     * @param array{lignes: list<array{variante: \App\Entity\ArticleVariante, quantite: int, total: int}>, total: int, messages: list<string>} $detail
     * @param Client $client
     *
     * @throws StockInsuffisantException
     * @throws PromoCodeException
     */
    private function passerCommande(array $detail, array $client, ?User $user, ?string $clientIp): Commande
    {
        $quantites = [];
        foreach ($detail['lignes'] as $ligne) {
            $quantites[(int) $ligne['variante']->getId()] = $ligne['quantite'];
        }

        return $this->commandes->passer($quantites, $client, $user, $clientIp);
    }

    /**
     * Pour un paiement au club (espèces/chèque), la commande est confirmée dès sa création : le
     * mail part tout de suite. Pour un paiement par carte, rien n'est encore payé à ce stade : le
     * mail de confirmation n'est envoyé qu'une fois le paiement réellement validé (voir paiement(),
     * paiementRetour() et helloAssoNotification()), jamais avant.
     */
    private function commandeReussie(Commande $commande): Response
    {
        $this->panier->clear();
        if (Commande::PAIEMENT_CARTE !== $commande->getModePaiement()) {
            $this->mailer->confirmation($commande);
        }
        $this->addFlash('boutique_success', 'Merci ! Votre commande est enregistrée.');

        return Commande::PAIEMENT_CARTE === $commande->getModePaiement()
            ? $this->redirectToRoute('boutique_paiement', $this->routeParams($commande))
            : $this->redirectToRoute('boutique_commande_suivi', $this->routeParams($commande));
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

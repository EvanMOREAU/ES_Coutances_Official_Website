<?php

namespace App\Controller;

use App\Controller\Concern\ResolvesCommande;
use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\Boutique\CommandeMailer;
use App\Service\Boutique\CommandeService;
use App\Service\HelloAsso\HelloAssoClient;
use App\Service\HelloAsso\HelloAssoException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Boutique du club : paiement par carte via HelloAsso (redirection, retour du client et notification serveur). */
#[Route('/boutique')]
class BoutiquePaiementController extends AbstractController implements BoutiqueAreaInterface
{
    use ResolvesCommande;

    public function __construct(
        private readonly CommandeService $commandes,
        private readonly CommandeMailer $mailer,
        private readonly HelloAssoClient $helloAsso,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
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
            $this->mailer->confirmation($commande);
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
            $this->mailer->confirmation($commande);
            $this->addFlash('boutique_success', 'Paiement reçu, merci !');
        } elseif (in_array($etat, ['Waiting', 'Processing'], true)) {
            $this->addFlash('boutique_warning', 'Votre paiement est en cours de traitement : nous vous confirmerons par e-mail dès sa validation.');
        } else {
            $this->addFlash('boutique_error', 'Le paiement n\'a pas abouti. Vous pouvez réessayer depuis cette page.');
        }

        return $suivi;
    }

    /**
     * Notification serveur à serveur envoyée par HelloAsso (à configurer dans leur back-office,
     * section Notifications, avec l'URL absolue de cette route) : contrairement au retour
     * navigateur (paiementRetour()), elle arrive même si le client ferme l'onglet après avoir
     * payé. On ne fait jamais confiance au contenu de la notification elle-même (rejouable) :
     * elle ne sert qu'à déclencher une revérification auprès de l'API, comme pour le retour.
     */
    #[Route('/helloasso/notification', name: 'boutique_helloasso_notification', methods: ['POST'])]
    public function helloAssoNotification(Request $request, CommandeRepository $commandes): Response
    {
        $payload = json_decode($request->getContent(), true);
        $checkoutIntentId = $this->extraireCheckoutIntentId(is_array($payload) ? $payload : []);
        if (null === $checkoutIntentId) {
            return new Response();
        }

        $commande = $commandes->findOneBy(['helloAssoCheckoutIntentId' => $checkoutIntentId]);
        if (!$commande || Commande::PAIEMENT_CARTE !== $commande->getModePaiement() || !$commande->isReglementDu()) {
            return new Response();
        }

        try {
            $intention = $this->helloAsso->recupererIntention($checkoutIntentId);
        } catch (HelloAssoException $e) {
            $this->logger->error('Échec de vérification (notification HelloAsso) de l\'intention {id} pour la commande {reference} : {message}', ['id' => $checkoutIntentId, 'reference' => $commande->getReference(), 'message' => $e->getMessage()]);

            // Code d'erreur : HelloAsso réessaiera d'envoyer cette notification plus tard.
            return new Response(status: 503);
        }

        if ('Authorized' === ($intention['state'] ?? null)) {
            $this->commandes->appliquer($commande, 'payer');
            $this->mailer->confirmation($commande);
        }

        return new Response();
    }

    /** Cherche récursivement un « checkoutIntentId » dans la charge utile, quel que soit le type d'évènement HelloAsso. */
    private function extraireCheckoutIntentId(mixed $payload): ?int
    {
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['checkoutIntentId']) && is_numeric($payload['checkoutIntentId'])) {
            return (int) $payload['checkoutIntentId'];
        }
        foreach ($payload as $value) {
            $trouve = $this->extraireCheckoutIntentId($value);
            if (null !== $trouve) {
                return $trouve;
            }
        }

        return null;
    }
}

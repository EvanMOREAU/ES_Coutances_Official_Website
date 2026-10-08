<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\Boutique\CommandeMailer;
use App\Service\Boutique\CommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Suivi des commandes de la boutique : à préparer, prêtes, retirées, règlements. */
#[Route('/admin/boutique/commandes')]
class CommandeController extends AbstractController
{
    #[Route('', name: 'admin_commande_index', methods: ['GET'])]
    public function index(CommandeRepository $repository): Response
    {
        return $this->render('admin/boutique/commande_index.html.twig', [
            'commandes' => $repository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    #[Route('/{id}', name: 'admin_commande_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        return $this->render('admin/boutique/commande_show.html.twig', ['commande' => $commande]);
    }

    #[Route('/{id}/{action}', name: 'admin_commande_action', requirements: ['id' => '\d+', 'action' => 'payer|preparer|retirer|annuler'], methods: ['POST'])]
    public function action(Request $request, Commande $commande, string $action, CommandeService $service, CommandeMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('commande-' . $commande->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée : jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_commande_show', ['id' => $commande->getId()]);
        }

        try {
            $service->appliquer($commande, $action);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_commande_show', ['id' => $commande->getId()]);
        }

        $prevenu = 'preparer' !== $action || $mailer->prete($commande);

        $this->addFlash($prevenu ? 'success' : 'error', match ($action) {
            'payer'    => 'Règlement enregistré.',
            'preparer' => $prevenu
                ? 'Commande prête à retirer : le client a été prévenu par email.'
                : 'Commande prête à retirer, mais l\'email au client n\'a pas pu être envoyé : prévenez-le autrement.',
            'retirer'  => 'Commande remise au client.',
            'annuler'  => 'Commande annulée, le stock a été remis à jour.',
        });

        return $this->redirectToRoute('admin_commande_show', ['id' => $commande->getId()]);
    }
}

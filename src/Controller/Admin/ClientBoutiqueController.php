<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Clients de la boutique : comptes limités à la boutique, et familles ou licenciés ayant déjà commandé (jamais l'équipe du club). */
#[Route('/admin/clients-boutique')]
class ClientBoutiqueController extends AbstractController
{
    #[Route('', name: 'admin_client_boutique_index', methods: ['GET'])]
    public function index(UserRepository $repository): Response
    {
        return $this->render('admin/boutique/client_index.html.twig', [
            'clients' => $repository->findBoutiqueClients(),
        ]);
    }

    /** Fiche d'un client : ses commandes, ses factures et l'historique de ses discussions avec le support. */
    #[Route('/{id}', name: 'admin_client_boutique_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        UserRepository $users,
        CommandeRepository $commandes,
        ConversationRepository $conversations,
        EntityManagerInterface $em,
    ): Response {
        $client = $this->client($id, $users);

        $orders = $commandes->findForUser($client);
        $paid   = array_filter($orders, static fn (Commande $c) => $c->isPayee() && Commande::STATUT_ANNULEE !== $c->getStatut());

        $discussions = [];
        foreach ($conversations->findSupportHistoryOf($client) as $conversation) {
            $discussions[] = [
                'conversation' => $conversation,
                'count'        => $em->getRepository(Message::class)->count(['conversation' => $conversation]),
            ];
        }

        return $this->render('admin/boutique/client_show.html.twig', [
            'client'      => $client,
            'commandes'   => $orders,
            'factures'    => array_values(array_filter($orders, static fn (Commande $c) => null !== $c->getNumeroFacture())),
            'totalPaye'   => array_sum(array_map(static fn (Commande $c) => $c->getTotalCentimes(), $paid)),
            'discussions' => $discussions,
        ]);
    }

    /** Une discussion de support passée, lue comme dans la messagerie (sans pouvoir y répondre). */
    #[Route('/{id}/discussions/{conversation}', name: 'admin_client_boutique_discussion', requirements: ['id' => '\d+', 'conversation' => '\d+'], methods: ['GET'])]
    public function discussion(int $id, Conversation $conversation, UserRepository $users, EntityManagerInterface $em): Response
    {
        $client = $this->client($id, $users);
        if (!$conversation->isSupport() || $conversation->getCustomer()?->getId() !== $client->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/boutique/client_discussion.html.twig', [
            'client'       => $client,
            'conversation' => $conversation,
            'messages'     => $em->getRepository(Message::class)->findBy(['conversation' => $conversation], ['id' => 'ASC']),
        ]);
    }

    private function client(int $id, UserRepository $users): User
    {
        $client = $users->find($id);
        if (!$client instanceof User || !$users->isBoutiqueClient($client)) {
            throw $this->createNotFoundException();
        }

        return $client;
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\FamilleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Historique des commandes de la boutique passées depuis un compte de l'espace familles / licenciés. */
class PortailCommandeController extends AbstractController
{
    #[Route('/mon-compte/commandes', name: 'portail_commandes', methods: ['GET'])]
    public function index(CommandeRepository $commandes, FamilleRepository $familles): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        // Un licencié mineur rattaché à un parent n'a pas accès à la partie administrative.
        if (!$familles->findOneBy(['user' => $user])) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('portail/commandes.html.twig', ['commandes' => $commandes->findForUser($user)]);
    }
}

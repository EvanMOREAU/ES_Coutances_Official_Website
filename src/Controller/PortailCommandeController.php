<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Historique des commandes de la boutique : accessible aux comptes famille et aux comptes
 * boutique (créés librement, sans famille ni licencié rattaché — voir RegistrationController).
 */
class PortailCommandeController extends AbstractController
{
    #[Route('/mon-compte/commandes', name: 'portail_commandes', methods: ['GET'])]
    public function index(CommandeRepository $commandes, FamilleRepository $familles, LicencieRepository $licencies): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        // Un licencié mineur rattaché à un parent n'a pas accès à la partie administrative
        // (un compte boutique, sans famille ni licencié, n'est pas concerné par cette règle).
        if (!$familles->findOneBy(['user' => $user]) && $licencies->findOneBy(['user' => $user])) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('portail/commandes.html.twig', ['commandes' => $commandes->findForUser($user)]);
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AdhesionRepository;
use App\Repository\FamilleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Factures des licences pour le titulaire d'une famille (parent, ou adulte qui gère lui-même
 * son compte) : montant, règlements et aides, saison après saison. Un licencié mineur rattaché
 * à un parent n'y a pas accès.
 */
class PortailFacturesController extends AbstractController
{
    #[Route('/mon-compte/factures', name: 'portail_factures', methods: ['GET'])]
    public function index(FamilleRepository $familles, AdhesionRepository $adhesions): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $famille = $familles->findOneBy(['user' => $user]) ?? throw $this->createAccessDeniedException();

        return $this->render('portail/factures.html.twig', ['adhesions' => $adhesions->forFamille($famille)]);
    }
}

<?php

namespace App\Controller;

use App\Repository\EquipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class NavController extends AbstractController
{
    public function equipes(EquipeRepository $equipeRepo): Response
    {
        $equipes = $equipeRepo->findBy([], ['ordre' => 'ASC']);

        $grouped = [];
        foreach ($equipes as $equipe) {
            $grouped[$equipe->getCategorie()][] = $equipe;
        }

        return $this->render('nav/_equipes_dropdown.html.twig', [
            'equipes_by_categorie' => $grouped,
        ]);
    }

    public function equipesMobile(EquipeRepository $equipeRepo): Response
    {
        $equipes = $equipeRepo->findBy([], ['ordre' => 'ASC']);

        $grouped = [];
        foreach ($equipes as $equipe) {
            $grouped[$equipe->getCategorie()][] = $equipe;
        }

        return $this->render('nav/_equipes_mobile.html.twig', [
            'equipes_by_categorie' => $grouped,
        ]);
    }
}
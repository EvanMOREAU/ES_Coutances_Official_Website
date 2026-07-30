<?php

namespace App\Controller;

use App\Repository\CategorieRepository;
use App\Repository\PageContenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/club')]
class ClubController extends AbstractController
{
    #[Route('/histoire', name: 'app_histoire')]
    public function histoire(PageContenuRepository $pageRepo): Response
    {
        $page = $pageRepo->findOneBy(['slug' => 'histoire']);

        if (!$page) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('club/histoire.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route('/encadrement', name: 'app_encadrement')]
    public function encadrement(CategorieRepository $categorieRepo): Response
    {
        // Catégories triées par ordre, avec leurs membres actifs
        $categories = $categorieRepo->findBy([], ['ordre' => 'ASC']);

        // Filtrer les catégories vides
        $categories = array_filter(
            $categories,
            fn($c) => $c->getMembres()->filter(fn($m) => $m->isActif())->count() > 0
        );

        return $this->render('club/encadrement.html.twig', [
            'categories' => array_values($categories),
        ]);
    }

    #[Route('/infrastructure', name: 'app_infrastructure')]
    public function infrastructure(PageContenuRepository $pageRepo): Response
    {
        $page = $pageRepo->findOneBy(['slug' => 'infrastructure']);

        if (!$page) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('club/infrastructure.html.twig', [
            'page' => $page,
        ]);
    }

}
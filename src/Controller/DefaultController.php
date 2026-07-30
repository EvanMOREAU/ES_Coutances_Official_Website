<?php

namespace App\Controller;

use App\Repository\EquipeRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SlideCarouselRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('')]
final class DefaultController extends AbstractController
{
#[Route('/', name: 'app_home')]
    public function index(
        SlideCarouselRepository $slideRepo,
        EquipeRepository        $equipeRepo,
        OffreEmploiRepository   $offreRepo,
        PartenaireRepository    $partenaireRepo,
    ): Response {
        // Slides actifs, triés par ordre
        $slides = $slideRepo->findBy(
            ['actif' => true],
            ['ordre' => 'ASC']
        );

        $partenairesCarousel = $partenaireRepo->findBy(
            ['actif' => true],
            ['ordre' => 'ASC']
        );
 
 
        // Équipes groupées par catégorie
        $equipes = $equipeRepo->findBy([], ['ordre' => 'ASC']);
        $equipesByCategorie = [];
        foreach ($equipes as $equipe) {
            $equipesByCategorie[$equipe->getCategorie()][] = $equipe;
        }
 
 
        // Offres d'emploi actives
        $offres = $offreRepo->findBy(['actif' => true]);
 
        return $this->render('default/index.html.twig', [
            'slides'               => $slides,
            'equipes_by_categorie' => $equipesByCategorie,
            'offres'               => $offres,
            'partenaires_carousel' => $partenairesCarousel,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\ChiffresClesRepository;
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
        OffreEmploiRepository   $offreRepo,
        PartenaireRepository    $partenaireRepo,
        ChiffresClesRepository  $chiffresClesRepo,
    ): Response {

        // Slides actifs, triés par ordre
        $slides = $slideRepo->findBy(
            ['actif' => true],
            ['ordre' => 'ASC']
        );

        // Partenaires actifs pour le carrousel
        $partenairesCarousel = $partenaireRepo->findBy(
            ['actif' => true],
            ['ordre' => 'ASC']
        );

        // Offres d'emploi actives
        $offres = $offreRepo->findBy(['actif' => true]);

        // Chiffres clés du club
        $chiffresCles = $chiffresClesRepo->getSingleton();

        // Render + cache HTTP 5 minutes
        $response = $this->render('default/index.html.twig', [
            'slides'               => $slides,
            'offres'               => $offres,
            'partenaires_carousel' => $partenairesCarousel,
            'chiffres_cles'        => $chiffresCles,
        ]);

        $response->setMaxAge(300);        // cache navigateur 5 min
        $response->setSharedMaxAge(300);  // cache proxy 5 min

        return $response;
    }
}

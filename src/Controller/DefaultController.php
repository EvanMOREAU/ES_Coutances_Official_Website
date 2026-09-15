<?php

namespace App\Controller;

use App\Repository\HomepageBannerRepository;
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
        HomepageBannerRepository $bannerRepo,
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

        // Bannière image + lien de la page d'accueil (si active)
        $banner = $bannerRepo->getSingleton();
        if ($banner && !$banner->isActif()) {
            $banner = null;
        }

        // Render + cache HTTP 5 minutes
        $response = $this->render('default/index.html.twig', [
            'slides'               => $slides,
            'offres'               => $offres,
            'partenaires_carousel' => $partenairesCarousel,
            'homepage_banner'      => $banner,
        ]);

        $response->setMaxAge(300);        // cache navigateur 5 min
        $response->setSharedMaxAge(300);  // cache proxy 5 min

        return $response;
    }
}

<?php

namespace App\Controller;

use App\Repository\PageContenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageDetailController extends AbstractController
{
    /** Pages de contenu qui ont une adresse dédiée : /page/<slug> y redirige (une seule URL par contenu). */
    private const DEDICATED_ROUTES = [
        'histoire'            => 'app_histoire',
        'infrastructure'      => 'app_infrastructure',
        'mentions-legales'    => 'app_legal_mentions',
        'confidentialite'     => 'app_legal_confidentialite',
        'conditions-de-vente' => 'app_legal_cgv',
        'cookies'             => 'app_legal_cookies',
    ];

    #[Route('/page/{slug}', name: 'app_page_detail')]
    public function show(string $slug, PageContenuRepository $pageRepo): Response
    {
        if (isset(self::DEDICATED_ROUTES[$slug])) {
            return $this->redirectToRoute(self::DEDICATED_ROUTES[$slug], [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $page = $pageRepo->findOneBy(['slug' => $slug]);

        if (!$page) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('default/page_detail.html.twig', [
            'page' => $page,
        ]);
    }
}

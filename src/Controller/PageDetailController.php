<?php

namespace App\Controller;

use App\Repository\PageContenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageDetailController extends AbstractController
{
    #[Route('/page/{slug}', name: 'app_page_detail')]
    public function show(string $slug, PageContenuRepository $pageRepo): Response
    {
        $page = $pageRepo->findOneBy(['slug' => $slug]);

        if (!$page) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('default/page_detail.html.twig', [
            'page' => $page,
        ]);
    }
}

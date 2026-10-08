<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\PageContenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Fichiers destinés aux moteurs de recherche : robots.txt et plan du site. */
final class SeoController extends AbstractController
{
    /** Routes publiques sans paramètre listées dans le plan du site. */
    private const STATIC_ROUTES = [
        'app_home', 'app_histoire', 'app_encadrement', 'app_infrastructure', 'app_contact', 'boutique_index',
        'app_legal_mentions', 'app_legal_confidentialite', 'app_legal_cgv', 'app_legal_cookies',
    ];

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /mon-compte',
            'Disallow: /reset-password',
            'Disallow: /boutique/panier',
            'Disallow: /boutique/commande',
            'Disallow: /boutique/code-promo',
            'Disallow: /health',
            '',
            'Sitemap: '.$this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return new Response(implode("\n", $lines)."\n", Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(PageContenuRepository $pages, ArticleRepository $articles): Response
    {
        $urls = [];
        foreach (self::STATIC_ROUTES as $route) {
            $urls[] = ['loc' => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => null];
        }
        foreach ($pages->findAll() as $page) {
            if (null === $page->getSlug()) {
                continue;
            }
            $urls[] = [
                'loc' => $this->generateUrl('app_page_detail', ['slug' => $page->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $page->getUpdatedAt(),
            ];
        }
        foreach ($articles->findVisibles() as $article) {
            $urls[] = [
                'loc' => $this->generateUrl('boutique_article', ['slug' => (string) $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $article->getUpdatedAt(),
            ];
        }

        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }
}

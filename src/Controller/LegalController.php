<?php

namespace App\Controller;

use App\Repository\ContactSettingsRepository;
use App\Repository\PageContenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages légales du site : mentions légales, politique de confidentialité, conditions de vente et
 * cookies. Le texte par défaut est fourni par les gabarits de templates/legal ; le club peut le
 * remplacer depuis l'administration (Site vitrine > Pages) en créant une page portant le même
 * identifiant (« mentions-legales », « confidentialite », « conditions-de-vente », « cookies »).
 *
 * Les informations propres à l'association (hébergeur, responsable de publication…) se règlent
 * par variables d'environnement (voir .env).
 */
final class LegalController extends AbstractController
{
    public function __construct(
        private readonly PageContenuRepository $pages,
        private readonly ContactSettingsRepository $contactSettings,
        #[Autowire('%env(LEGAL_PUBLICATION_DIRECTOR)%')] private readonly string $director,
        #[Autowire('%env(LEGAL_ASSOCIATION_ID)%')] private readonly string $associationId,
        #[Autowire('%env(LEGAL_HOST)%')] private readonly string $host,
        #[Autowire('%env(LEGAL_CONSUMER_MEDIATOR)%')] private readonly string $mediator,
    ) {
    }

    #[Route('/mentions-legales', name: 'app_legal_mentions', methods: ['GET'])]
    public function mentions(): Response
    {
        return $this->page('mentions-legales', 'legal/mentions.html.twig');
    }

    #[Route('/politique-de-confidentialite', name: 'app_legal_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->page('confidentialite', 'legal/confidentialite.html.twig');
    }

    #[Route('/conditions-de-vente', name: 'app_legal_cgv', methods: ['GET'])]
    public function conditionsDeVente(): Response
    {
        return $this->page('conditions-de-vente', 'legal/cgv.html.twig');
    }

    #[Route('/cookies', name: 'app_legal_cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->page('cookies', 'legal/cookies.html.twig');
    }

    private function page(string $slug, string $template): Response
    {
        $custom = $this->pages->findOneBy(['slug' => $slug]);
        if (null !== $custom) {
            return $this->render('default/page_detail.html.twig', ['page' => $custom]);
        }

        return $this->render($template, [
            'contact'        => $this->contactSettings->getSingleton(),
            'director'       => $this->director,
            'association_id' => $this->associationId,
            'host'           => $this->host,
            'mediator'       => $this->mediator,
        ]);
    }
}

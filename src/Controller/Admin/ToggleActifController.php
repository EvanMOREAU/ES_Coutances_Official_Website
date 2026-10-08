<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Membre;
use App\Entity\OffreEmploi;
use App\Entity\Partenaire;
use App\Entity\SlideCarousel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Active ou désactive un élément du site vitrine directement depuis le menu « … » de sa ligne,
 * sans ouvrir le formulaire de modification.
 */
#[Route('/admin/basculer/{type}/{id}', name: 'admin_toggle_actif', requirements: ['type' => '[a-z_]+', 'id' => '\d+'], methods: ['POST'])]
class ToggleActifController extends AbstractController
{
    /** type => [classe, route de la liste, libellé] */
    private const TYPES = [
        'partenaire'     => [Partenaire::class, 'admin_partenaire_index', 'Partenaire'],
        'offre_emploi'   => [OffreEmploi::class, 'admin_offre_emploi_index', 'Offre'],
        'slide_carousel' => [SlideCarousel::class, 'admin_slide_carousel_index', 'Slide'],
        'article'        => [Article::class, 'admin_article_index', 'Article'],
        'membre'         => [Membre::class, 'admin_membre_index', 'Membre'],
    ];

    public function __invoke(string $type, int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!isset(self::TYPES[$type])) {
            throw $this->createNotFoundException();
        }
        [$class, $route, $label] = self::TYPES[$type];

        if ($this->isCsrfTokenValid(sprintf('toggle-%s-%d', $type, $id), (string) $request->request->get('_token'))) {
            $item = $em->find($class, $id) ?? throw $this->createNotFoundException();
            $item->setActif(!$item->isActif());
            $em->flush();
            $this->addFlash('success', $item->isActif() ? 'Élément activé : il est de nouveau visible sur le site.' : 'Élément désactivé : il n’est plus visible sur le site.');
        }

        return $this->redirectToRoute($route);
    }
}

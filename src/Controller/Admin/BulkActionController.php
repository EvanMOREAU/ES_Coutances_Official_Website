<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Equipe;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Membre;
use App\Entity\OffreEmploi;
use App\Entity\PageContenu;
use App\Entity\Partenaire;
use App\Entity\RejoindreCard;
use App\Entity\Saison;
use App\Entity\SlideCarousel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions groupées des tableaux de l'admin : changement de statut et
 * suppression sur plusieurs lignes sélectionnées.
 */
#[Route('/admin/actions-groupees')]
class BulkActionController extends AbstractController
{
    /** type => [classe, route de la liste, libellé au pluriel, gère les statuts] */
    private const TYPES = [
        'famille'        => [Famille::class, 'admin_famille_index', 'familles', true],
        'licencie'       => [Licencie::class, 'admin_licencie_index', 'licenciés', true],
        'equipe'         => [Equipe::class, 'admin_equipe_index', 'équipes', true],
        'saison'         => [Saison::class, 'admin_saison_index', 'saisons', true],
        'article'        => [Article::class, 'admin_article_index', 'articles', true],
        'partenaire'     => [Partenaire::class, 'admin_partenaire_index', 'partenaires', true],
        'membre'         => [Membre::class, 'admin_membre_index', 'membres', false],
        'offre_emploi'   => [OffreEmploi::class, 'admin_offre_emploi_index', "offres d'emploi", false],
        'slide_carousel' => [SlideCarousel::class, 'admin_slide_carousel_index', 'slides', false],
        'page_contenu'   => [PageContenu::class, 'admin_page_contenu_index', 'pages', false],
        'rejoindre_card' => [RejoindreCard::class, 'admin_rejoindre_card_index', 'cartes', false],
    ];

    #[Route('/{type}', name: 'admin_bulk_action', requirements: ['type' => '[a-z_]+'], methods: ['POST'])]
    public function __invoke(string $type, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!isset(self::TYPES[$type])) {
            throw $this->createNotFoundException();
        }
        [$class, $indexRoute, $plural, $hasStatut] = self::TYPES[$type];

        if (!$this->isCsrfTokenValid('bulk-'.$type, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée : jeton de sécurité invalide.');

            return $this->redirectToRoute($indexRoute);
        }

        $ids = array_values(array_filter(array_map('intval', $request->request->all('ids'))));
        if ([] === $ids) {
            $this->addFlash('error', 'Aucun élément sélectionné.');

            return $this->redirectToRoute($indexRoute);
        }

        $entities = $em->getRepository($class)->findBy(['id' => $ids]);
        $action   = (string) $request->request->get('action');

        if ('delete' === $action) {
            $deleted = 0;
            $blocked = 0;
            foreach ($entities as $entity) {
                if (null !== $this->deletionBlocker($entity, $em)) {
                    ++$blocked;
                    continue;
                }
                $em->remove($entity);
                ++$deleted;
            }
            $em->flush();

            if ($deleted > 0) {
                $this->addFlash('success', sprintf('%d élément(s) supprimé(s) (%s).', $deleted, $plural));
            }
            if ($blocked > 0) {
                $this->addFlash('error', sprintf('%d élément(s) non supprimé(s) : ils contiennent encore des licenciés. Supprimez d\'abord les licenciés concernés.', $blocked));
            }
        } elseif ($hasStatut && str_starts_with($action, 'statut:')) {
            $statut = substr($action, 7);
            if (!isset(Equipe::STATUTS[$statut])) {
                $this->addFlash('error', 'Statut inconnu.');

                return $this->redirectToRoute($indexRoute);
            }
            foreach ($entities as $entity) {
                $entity->setStatut($statut);
            }
            $em->flush();

            $this->addFlash('success', sprintf('%d élément(s) passé(s) en « %s ».', count($entities), mb_strtolower(Equipe::STATUTS[$statut])));
        } else {
            $this->addFlash('error', 'Action inconnue.');
        }

        return $this->redirectToRoute($indexRoute);
    }

    /** Une famille ou une saison encore référencée par des licenciés ne peut pas être supprimée. */
    private function deletionBlocker(object $entity, EntityManagerInterface $em): ?string
    {
        if ($entity instanceof Famille && !$entity->getLicencies()->isEmpty()) {
            return 'famille';
        }
        if ($entity instanceof Saison && $em->getRepository(Licencie::class)->count(['saison' => $entity]) > 0) {
            return 'saison';
        }

        return null;
    }
}

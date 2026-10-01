<?php

namespace App\Controller\Admin;

use App\Repository\ContratPartenaireRepository;
use App\Repository\PartenaireRepository;
use App\Service\SiteAdvisor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'entrée de la gestion des partenaires : une carte par rubrique, sur le
 * même principe que le hub « Site vitrine » (voir SiteVitrineController).
 * Pour l'instant, une seule rubrique : la liste des partenaires & sponsors.
 */
#[Route('/admin/partenaires/hub', name: 'admin_partenaire_hub', methods: ['GET'])]
class PartenaireHubController extends AbstractController
{
    private const PER_PAGE = 10;

    public function __invoke(Request $request, SiteAdvisor $advisor, PartenaireRepository $partenaires, ContratPartenaireRepository $contrats): Response
    {
        $partenairesActifs = $partenaires->count(['statut' => 'active']);

        $pageImpayes = max(1, $request->query->getInt('page_impayes', 1));
        $pageTaches  = max(1, $request->query->getInt('page_taches', 1));
        $impayes     = $contrats->findAvecResteAPayer($pageImpayes, self::PER_PAGE);
        $taches      = $contrats->tachesEnAttente($pageTaches, self::PER_PAGE);

        $sections = [[
            'key'    => 'partenaires',
            'group'  => 'Partenaires',
            'icon'   => 'fa-handshake',
            'title'  => 'Partenaires & sponsors',
            'url'    => $this->generateUrl('admin_partenaire_index'),
            'new'    => $this->generateUrl('admin_partenaire_new'),
            'value'  => $partenairesActifs,
            'label'  => 'actifs',
            'sub'    => sprintf('sur %d au total', $partenaires->count([])),
            'visual' => ['type' => 'line', 'points' => array_map(
                static fn (array $p) => ['month' => $p['month']->format('Y-m'), 'total' => $p['total'], 'new' => $p['new']],
                $partenaires->monthlyEvolution(12),
            ), 'unit' => 'partenaire'],
        ]];

        return $this->render('admin/partenaire/hub.html.twig', [
            'sections'          => $sections,
            'advice'            => $advisor->items(SiteAdvisor::HUB_PARTENAIRE),
            'totalMontant'      => $contrats->totalMontantCentimes(),
            'impayes'           => $impayes['rows'],
            'totalImpayes'      => $impayes['total'],
            'pageImpayes'       => $pageImpayes,
            'pagesImpayes'      => max(1, (int) ceil($impayes['total'] / self::PER_PAGE)),
            'totalResteAPayer'  => $impayes['totalResteCentimes'],
            'tachesEnAttente'   => $taches['rows'],
            'totalTaches'       => $taches['total'],
            'pageTaches'        => $pageTaches,
            'pagesTaches'       => max(1, (int) ceil($taches['total'] / self::PER_PAGE)),
            // ?ouvrir=partenaires[&vue=new] : ouvre directement la fenêtre de la rubrique (liens des conseils, de la cloche...)
            'open'     => (string) $request->query->get('ouvrir', ''),
            'openView' => 'new' === $request->query->get('vue') ? 'new' : 'list',
        ]);
    }
}

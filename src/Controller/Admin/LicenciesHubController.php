<?php

namespace App\Controller\Admin;

use App\Repository\EquipeRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Repository\SaisonRepository;
use App\Service\SiteAdvisor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue d'ensemble de la gestion des licenciés : une carte (chiffres + graphique)
 * par rubrique — familles, licenciés, équipes, saisons — qui ouvre la gestion
 * dans une grande fenêtre, comme la page « Site vitrine ».
 */
#[Route('/admin/gestion-licencies', name: 'admin_licencies_hub', methods: ['GET'])]
class LicenciesHubController extends AbstractController
{
    public function __invoke(
        Request $request,
        SiteAdvisor $advisor,
        FamilleRepository $familles,
        LicencieRepository $licencies,
        EquipeRepository $equipes,
        SaisonRepository $saisons,
    ): Response {
        // Licenciés actifs avec saison et équipes chargées d'un coup (catégorie d'âge calculée en PHP).
        $actifs = $licencies->createQueryBuilder('l')
            ->addSelect('s', 'e')
            ->join('l.saison', 's')
            ->leftJoin('l.equipes', 'e')
            ->andWhere('l.statut = :actif')
            ->setParameter('actif', 'active')
            ->getQuery()
            ->getResult();

        $parCategorie = [];
        $parEquipe    = [];
        foreach ($actifs as $licencie) {
            $categorie = $licencie->getCategorie() ?? '—';
            $parCategorie[$categorie] = ($parCategorie[$categorie] ?? 0) + 1;
            foreach ($licencie->getEquipes() as $equipe) {
                $parEquipe[$equipe->getId()] = ($parEquipe[$equipe->getId()] ?? 0) + 1;
            }
        }

        $sections = [];

        // --- Familles : combien de licenciés par famille ---------------------
        $tailles = array_count_values(array_map('intval', array_column(
            $familles->createQueryBuilder('f')
                ->select('COUNT(l.id) AS n')
                ->leftJoin('f.licencies', 'l')
                ->groupBy('f.id')
                ->getQuery()->getScalarResult(),
            'n',
        )));
        $sections[] = [
            'key'    => 'famille',
            'group'  => 'Gestion',
            'icon'   => 'fa-house-user',
            'title'  => 'Familles',
            'url'    => $this->generateUrl('admin_famille_index'),
            'new'    => $this->generateUrl('admin_famille_new'),
            'value'  => $familles->count(['statut' => 'active']),
            'label'  => 'actives',
            'sub'    => sprintf('sur %d au total', $familles->count([])),
            'visual' => $this->bars('Licenciés par famille', [
                ['label' => 'Aucun', 'value' => $tailles[0] ?? 0],
                ['label' => '1 licencié', 'value' => $tailles[1] ?? 0],
                ['label' => '2 licenciés', 'value' => $tailles[2] ?? 0],
                ['label' => '3 et plus', 'value' => array_sum(array_filter($tailles, static fn ($n, $taille) => $taille >= 3, ARRAY_FILTER_USE_BOTH))],
            ]),
        ];

        // --- Licenciés : répartition par catégorie d'âge ---------------------
        arsort($parCategorie);
        $items = [];
        foreach (array_slice($parCategorie, 0, 5, true) as $categorie => $count) {
            $items[] = ['label' => (string) $categorie, 'value' => $count];
        }
        $sections[] = [
            'key'    => 'licencie',
            'group'  => 'Gestion',
            'icon'   => 'fa-id-card',
            'title'  => 'Licenciés',
            'url'    => $this->generateUrl('admin_licencie_index'),
            'new'    => $this->generateUrl('admin_licencie_new'),
            'value'  => count($actifs),
            'label'  => 'actifs',
            'sub'    => sprintf('sur %d au total', $licencies->count([])),
            'visual' => $this->bars('Principales catégories', $items),
        ];

        // --- Équipes : licenciés par équipe ----------------------------------
        $equipesActives = $equipes->findBy(['statut' => 'active'], ['categorie' => 'ASC', 'nom' => 'ASC']);
        $items = array_map(
            static fn ($e) => ['label' => $e->getNom(), 'value' => $parEquipe[$e->getId()] ?? 0],
            $equipesActives,
        );
        usort($items, static fn ($a, $b) => $b['value'] <=> $a['value']);
        $sections[] = [
            'key'    => 'equipe',
            'group'  => 'Gestion',
            'icon'   => 'fa-shirt',
            'title'  => 'Équipes',
            'url'    => $this->generateUrl('admin_equipe_index'),
            'new'    => $this->generateUrl('admin_equipe_new'),
            'value'  => count($equipesActives),
            'label'  => 'actives',
            'sub'    => sprintf('sur %d au total', $equipes->count([])),
            'visual' => $this->bars('Licenciés par équipe', array_slice($items, 0, 5)),
        ];

        // --- Saisons : licenciés par saison ----------------------------------
        $recentes = $saisons->findBy([], ['dateDebut' => 'DESC'], 4);
        $active   = $saisons->findActive();
        $sections[] = [
            'key'    => 'saison',
            'group'  => 'Gestion',
            'icon'   => 'fa-calendar-days',
            'title'  => 'Saisons',
            'url'    => $this->generateUrl('admin_saison_index'),
            'new'    => $this->generateUrl('admin_saison_new'),
            'value'  => $active?->getLibelle() ?? '—',
            'label'  => 'en cours',
            'sub'    => sprintf('%d saison%s enregistrée%s', $saisons->count([]), $saisons->count([]) > 1 ? 's' : '', $saisons->count([]) > 1 ? 's' : ''),
            'visual' => $this->bars('Licenciés par saison', array_map(
                static fn ($s) => ['label' => $s->getLibelle(), 'value' => $licencies->count(['saison' => $s])],
                $recentes,
            )),
        ];

        return $this->render('admin/licencies_hub.html.twig', [
            'sections' => $sections,
            'advice'   => $advisor->items(SiteAdvisor::HUB_LICENCIES),
            'open'     => (string) $request->query->get('ouvrir', ''),
            'openView' => 'new' === $request->query->get('vue') ? 'new' : 'list',
        ]);
    }

    /** @param list<array{label: string, value: int}> $items */
    private function bars(string $caption, array $items): array
    {
        return [
            'type'    => 'bars',
            'caption' => $caption,
            'items'   => $items,
            'max'     => max(1, ...array_column($items ?: [['value' => 1]], 'value')),
        ];
    }
}

<?php

namespace App\Controller\Admin;

use App\Repository\SaisonRepository;
use App\Service\EffectifsStatistics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Statistiques d'effectifs : licenciés par catégorie, évolution et taux de réinscription d'une saison à l'autre. */
#[Route('/admin/statistiques-effectifs', name: 'admin_statistiques_effectifs', methods: ['GET'])]
final class EffectifsStatisticsController extends AbstractController
{
    public function __invoke(Request $request, EffectifsStatistics $statistics, SaisonRepository $saisons): Response
    {
        $id     = $request->query->getInt('saison');
        $saison = $id > 0 ? $saisons->find($id) : $saisons->findActive();

        return $this->render('admin/statistiques/effectifs.html.twig', [
            'stats'   => $statistics->pour($saison),
            'saisons' => $saisons->findBy([], ['dateDebut' => 'DESC']),
        ]);
    }
}

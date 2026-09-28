<?php

namespace App\Controller\Admin;

use App\Repository\EquipeRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Repository\MembreRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SaisonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin', methods: ['GET'])]
#[IsGranted('ROLE_EDITOR')]
class AdminDashboardController extends AbstractController
{
    /** Nombre d'éléments affichés dans chaque bloc « derniers créés ». */
    private const LATEST = 5;

    public function __invoke(
        FamilleRepository $familleRepository,
        LicencieRepository $licencieRepository,
        SaisonRepository $saisonRepository,
        PartenaireRepository $partenaireRepository,
        OffreEmploiRepository $offreEmploiRepository,
        MembreRepository $membreRepository,
        EquipeRepository $equipeRepository,
    ): Response {
        // Les listes nominatives sont réservées aux administrateurs (comme les pages Familles, Licenciés...).
        $canSeeLicencies = $this->isGranted('ROLE_ADMIN');

        return $this->render('admin/dashboard_home.html.twig', [
            'stats' => [
                ['label' => 'Familles',              'value' => $familleRepository->count([]),                   'icon' => 'users',        'trend' => null],
                ['label' => 'Licenciés actifs',       'value' => $licencieRepository->count(['actif' => true]),   'icon' => 'id-card',      'trend' => $this->licenciesTrend($licencieRepository, $saisonRepository)],
                ['label' => 'Partenaires actifs',     'value' => $partenaireRepository->count(['actif' => true]), 'icon' => 'handshake',    'trend' => null],
                ["label" => "Offres d'emploi actives",'value' => $offreEmploiRepository->count(['actif' => true]),'icon' => 'briefcase',    'trend' => null],
                ['label' => 'Membres encadrement',    'value' => $membreRepository->count(['actif' => true]),     'icon' => 'people-group', 'trend' => null],
            ],
            'dernieres_familles'   => $canSeeLicencies ? $familleRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'derniers_licencies'   => $canSeeLicencies ? $licencieRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'dernieres_equipes'    => $canSeeLicencies ? $equipeRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'dernieres_saisons'    => $canSeeLicencies ? $saisonRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'partenaires_evolution' => $partenaireRepository->monthlyEvolution(12),
        ]);
    }

    /**
     * Évolution du nombre de licenciés actifs entre la saison active et la
     * saison précédente. Retourne null tant qu'il n'y a pas deux saisons à
     * comparer (pas de donnée fabriquée).
     */
    private function licenciesTrend(LicencieRepository $licencieRepository, SaisonRepository $saisonRepository): ?array
    {
        $saisonActive = $saisonRepository->findActive();
        if (!$saisonActive) {
            return null;
        }

        $saisonPrecedente = $saisonRepository->findPrevious($saisonActive);
        if (!$saisonPrecedente) {
            return null;
        }

        $actuel     = $licencieRepository->countActifsBySaison($saisonActive);
        $precedent  = $licencieRepository->countActifsBySaison($saisonPrecedente);

        if (0 === $precedent) {
            return null;
        }

        $variation = round((($actuel - $precedent) / $precedent) * 100, 1);

        return [
            'value'    => sprintf('%+g%%', $variation),
            'positive' => $variation >= 0,
            'label'    => 'vs ' . $saisonPrecedente->getLibelle(),
        ];
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\EquipeRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Repository\MembreRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SaisonRepository;
use App\Security\PermissionChecker;
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

    /**
     * Catalogue des modules personnalisables du tableau de bord : clé => [libellé, autorisation
     * requise (null = accessible à tout utilisateur de l'admin)]. Un module n'est ni proposé dans
     * le menu « Personnaliser » ni calculé/rendu si l'autorisation manque.
     */
    private const WIDGETS = [
        'advice'                => ['label' => 'Conseils de configuration', 'permission' => null],
        'partenaires_evolution' => ['label' => 'Évolution des partenaires', 'permission' => 'partenaire.voir'],
        'licencies_evolution'   => ['label' => 'Évolution des licenciés',   'permission' => 'licencie.voir'],
        'boutique_ventes'       => ['label' => 'Ventes de la boutique',     'permission' => 'commande.voir'],
        'quick_links'           => ['label' => 'Accès rapides',            'permission' => null],
        'dernieres_familles'    => ['label' => 'Dernières familles',       'permission' => 'famille.voir'],
        'derniers_licencies'    => ['label' => 'Derniers licenciés',       'permission' => 'licencie.voir'],
        'dernieres_equipes'     => ['label' => 'Dernières équipes',        'permission' => 'equipe.voir'],
        'dernieres_saisons'     => ['label' => 'Dernières saisons',        'permission' => 'saison.voir'],
    ];

    public function __invoke(
        FamilleRepository $familleRepository,
        LicencieRepository $licencieRepository,
        SaisonRepository $saisonRepository,
        PartenaireRepository $partenaireRepository,
        OffreEmploiRepository $offreEmploiRepository,
        MembreRepository $membreRepository,
        EquipeRepository $equipeRepository,
        CommandeRepository $commandeRepository,
        PermissionChecker $permissions,
    ): Response {
        $available = array_filter(self::WIDGETS, static fn (array $w) => !$w['permission'] || $permissions->can($w['permission']));

        $user = $this->getUser();
        $hidden = $user instanceof User ? ($user->getTablePreferences()['dashboard']['hidden'] ?? []) : [];

        $stats = array_filter([
            ['label' => 'Familles',               'value' => $familleRepository->count([]),                    'icon' => 'users',        'trend' => null, 'permission' => 'famille.voir'],
            ['label' => 'Licenciés actifs',        'value' => $licencieRepository->count(['actif' => true]),    'icon' => 'id-card',      'trend' => $this->licenciesTrend($licencieRepository, $saisonRepository), 'permission' => 'licencie.voir'],
            ['label' => 'Partenaires actifs',      'value' => $partenaireRepository->count(['actif' => true]),  'icon' => 'handshake',    'trend' => null, 'permission' => 'partenaire.voir'],
            ["label" => "Offres d'emploi actives", 'value' => $offreEmploiRepository->count(['actif' => true]), 'icon' => 'briefcase',    'trend' => null, 'permission' => 'offre_emploi.voir'],
            ['label' => 'Membres encadrement',     'value' => $membreRepository->count(['actif' => true]),      'icon' => 'people-group', 'trend' => null, 'permission' => 'membre.voir'],
        ], static fn (array $s) => $permissions->can($s['permission']));

        return $this->render('admin/dashboard_home.html.twig', [
            'available'             => $available,
            'hidden'                => $hidden,
            'stats'                 => array_values($stats),
            'dernieres_familles'    => isset($available['dernieres_familles']) ? $familleRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'derniers_licencies'    => isset($available['derniers_licencies']) ? $licencieRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'dernieres_equipes'     => isset($available['dernieres_equipes']) ? $equipeRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'dernieres_saisons'     => isset($available['dernieres_saisons']) ? $saisonRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'partenaires_evolution' => isset($available['partenaires_evolution']) ? $partenaireRepository->monthlyEvolution(12) : [],
            'licencies_evolution'   => isset($available['licencies_evolution']) ? $licencieRepository->monthlyEvolution(12) : [],
            'boutique_ventes'       => isset($available['boutique_ventes']) ? $commandeRepository->monthlyRevenue(12) : [],
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

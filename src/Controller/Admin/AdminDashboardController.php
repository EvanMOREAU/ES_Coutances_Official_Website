<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\EquipeRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\PartenaireRepository;
use App\Repository\SaisonRepository;
use App\Security\PermissionChecker;
use App\Service\Dashboard\DashboardStatistics;
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
     * Catalogue des modules personnalisables du tableau de bord : clé => [clé de traduction du libellé, section,
     * autorisation requise (null = accessible à tout utilisateur de l'admin)]. Un module n'est ni proposé dans
     * le menu « Personnaliser » ni calculé/rendu si l'autorisation manque.
     */
    private const WIDGETS = [
        'advice'               => ['label' => 'dashboard.widget.advice',               'section' => 'overview',  'permission' => null],
        'quick_links'          => ['label' => 'dashboard.widget.quick_links',          'section' => 'overview',  'permission' => null],
        'licencies_evolution'  => ['label' => 'dashboard.widget.members_evolution',    'section' => 'members',   'permission' => 'licencie.voir'],
        'effectifs_categories' => ['label' => 'dashboard.widget.members_categories',   'section' => 'members',   'permission' => 'licencie.voir'],
        'licencies_saisons'    => ['label' => 'dashboard.widget.members_seasons',      'section' => 'members',   'permission' => 'licencie.voir'],
        'licencies_equipes'    => ['label' => 'dashboard.widget.members_teams',        'section' => 'members',   'permission' => 'licencie.voir'],
        'licencies_genre'      => ['label' => 'dashboard.widget.members_gender',       'section' => 'members',   'permission' => 'licencie.voir'],
        'familles_evolution'   => ['label' => 'dashboard.widget.families_evolution',   'section' => 'members',   'permission' => 'famille.voir'],
        'familles_tailles'     => ['label' => 'dashboard.widget.families_sizes',       'section' => 'members',   'permission' => 'famille.voir'],
        'paiements_statuts'    => ['label' => 'dashboard.widget.payments_status',      'section' => 'payments',  'permission' => 'adhesion.voir'],
        'paiements_encaisse'   => ['label' => 'dashboard.widget.payments_collected',   'section' => 'payments',  'permission' => 'adhesion.voir'],
        'reglements_mois'      => ['label' => 'dashboard.widget.payments_monthly',     'section' => 'payments',  'permission' => 'adhesion.voir'],
        'reglements_modes'     => ['label' => 'dashboard.widget.payments_methods',     'section' => 'payments',  'permission' => 'adhesion.voir'],
        'boutique_ventes'      => ['label' => 'dashboard.widget.shop_sales',           'section' => 'shop',      'permission' => 'commande.voir'],
        'boutique_commandes'   => ['label' => 'dashboard.widget.shop_orders',          'section' => 'shop',      'permission' => 'commande.voir'],
        'boutique_statuts'     => ['label' => 'dashboard.widget.shop_status',          'section' => 'shop',      'permission' => 'commande.voir'],
        'boutique_top'         => ['label' => 'dashboard.widget.shop_top',             'section' => 'shop',      'permission' => 'commande.voir'],
        'partenaires_evolution' => ['label' => 'dashboard.widget.partners_evolution',  'section' => 'partners',  'permission' => 'partenaire.voir'],
        'partenaires_categories' => ['label' => 'dashboard.widget.partners_categories', 'section' => 'partners', 'permission' => 'partenaire.voir'],
        'contrats_encaissement' => ['label' => 'dashboard.widget.contracts_collected', 'section' => 'partners',  'permission' => 'partenaire.voir'],
        'contrats_taches'      => ['label' => 'dashboard.widget.contracts_tasks',      'section' => 'partners',  'permission' => 'partenaire.voir'],
        'planning_prochains'   => ['label' => 'dashboard.widget.planning_next',        'section' => 'planning',  'permission' => 'planning.voir'],
        'planning_types'       => ['label' => 'dashboard.widget.planning_types',       'section' => 'planning',  'permission' => 'planning.voir'],
        'dernieres_familles'   => ['label' => 'dashboard.widget.latest_families',      'section' => 'latest',    'permission' => 'famille.voir'],
        'derniers_licencies'   => ['label' => 'dashboard.widget.latest_members',       'section' => 'latest',    'permission' => 'licencie.voir'],
        'dernieres_equipes'    => ['label' => 'dashboard.widget.latest_teams',         'section' => 'latest',    'permission' => 'equipe.voir'],
        'dernieres_saisons'    => ['label' => 'dashboard.widget.latest_seasons',       'section' => 'latest',    'permission' => 'saison.voir'],
    ];

    public function __invoke(
        FamilleRepository $familleRepository,
        LicencieRepository $licencieRepository,
        SaisonRepository $saisonRepository,
        PartenaireRepository $partenaireRepository,
        OffreEmploiRepository $offreEmploiRepository,
        EquipeRepository $equipeRepository,
        CommandeRepository $commandeRepository,
        PermissionChecker $permissions,
        DashboardStatistics $statistics,
    ): Response {
        $available = array_filter(self::WIDGETS, static fn (array $w) => !$w['permission'] || $permissions->can($w['permission']));
        $has       = static fn (string $key): bool => isset($available[$key]);

        $user   = $this->getUser();
        $hidden = $user instanceof User ? ($user->getTablePreferences()['dashboard']['hidden'] ?? []) : [];

        // Tous les modules autorisés sont calculés, même masqués : l'utilisateur peut les réafficher sans recharger la page.
        $needs = $has;

        $data = [];
        if ($needs('effectifs_categories')) {
            $data['effectifs'] = $statistics->effectifs();
        }
        if ($needs('licencies_saisons')) {
            $data['licencies_saisons'] = $statistics->licenciesParSaison();
        }
        if ($needs('licencies_equipes')) {
            $data['licencies_equipes'] = $statistics->licenciesParEquipe();
        }
        if ($needs('licencies_genre')) {
            $data['licencies_genre'] = $statistics->sexes();
        }
        if ($needs('familles_evolution')) {
            $data['familles_evolution'] = $statistics->famillesParMois();
        }
        if ($needs('familles_tailles')) {
            $data['familles_tailles'] = $statistics->taillesFamilles();
        }
        if ($permissions->can('adhesion.voir')) {
            $data['paiements'] = $statistics->paiements();
        }
        if ($needs('reglements_mois')) {
            $data['reglements_mois'] = $statistics->reglementsParMois();
        }
        if ($needs('reglements_modes')) {
            $data['reglements_modes'] = $statistics->modesReglement();
        }
        if ($has('boutique_ventes')) {
            $data['boutique'] = $statistics->boutique();
        }
        if ($needs('boutique_commandes')) {
            $data['boutique_commandes'] = $statistics->commandesParMois();
        }
        if ($needs('boutique_statuts')) {
            $data['boutique_statuts'] = $statistics->commandesParStatut();
        }
        if ($needs('boutique_top')) {
            $data['boutique_top'] = $statistics->topArticles();
        }
        if ($needs('partenaires_categories')) {
            $data['partenaires_categories'] = $statistics->partenairesParCategorie();
        }
        if ($needs('contrats_encaissement')) {
            $data['contrats'] = $statistics->contrats();
        }
        if ($needs('contrats_taches')) {
            $data['taches'] = $statistics->taches();
        }
        if ($has('planning_prochains')) {
            $data['prochains'] = $statistics->prochainsEvenements();
        }
        if ($needs('planning_types')) {
            $data['types_evenements'] = $statistics->typesEvenements();
        }

        $stats = array_filter([
            ['key' => 'families',  'value' => $familleRepository->count([]),                    'icon' => 'users',           'trend' => null, 'permission' => 'famille.voir',     'route' => 'admin_famille_index'],
            ['key' => 'members',   'value' => $licencieRepository->count(['actif' => true]),    'icon' => 'id-card',         'trend' => $this->licenciesTrend($licencieRepository, $saisonRepository), 'permission' => 'licencie.voir', 'route' => 'admin_licencie_index'],
            ['key' => 'partners',  'value' => $partenaireRepository->count(['actif' => true]),  'icon' => 'handshake',       'trend' => null, 'permission' => 'partenaire.voir',  'route' => 'admin_partenaire_index'],
            ['key' => 'jobs',      'value' => $offreEmploiRepository->count(['actif' => true]), 'icon' => 'briefcase',       'trend' => null, 'permission' => 'offre_emploi.voir', 'route' => 'admin_offre_emploi_index'],
        ], static fn (array $s) => $permissions->can($s['permission']));

        return $this->render('admin/dashboard_home.html.twig', [
            'available'             => $available,
            'hidden'                => $hidden,
            'stats'                 => array_values($stats),
            'data'                  => $data,
            'dernieres_familles'    => isset($available['dernieres_familles']) ? $familleRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'derniers_licencies'    => isset($available['derniers_licencies']) ? $licencieRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'dernieres_equipes'     => isset($available['dernieres_equipes']) ? $equipeRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'dernieres_saisons'     => isset($available['dernieres_saisons']) ? $saisonRepository->findBy([], ['id' => 'DESC'], self::LATEST) : [],
            'partenaires_evolution' => $needs('partenaires_evolution') ? $partenaireRepository->monthlyEvolution(12) : [],
            'licencies_evolution'   => $needs('licencies_evolution') ? $licencieRepository->monthlyEvolution(12) : [],
            'boutique_ventes'       => $needs('boutique_ventes') ? $commandeRepository->monthlyRevenue(12) : [],
        ]);
    }

    /**
     * Évolution du nombre de licenciés actifs entre la saison active et la
     * saison précédente. Retourne null tant qu'il n'y a pas deux saisons à
     * comparer (pas de donnée fabriquée).
     *
     * @return array{value: string, positive: bool, previous: string}|null
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

        $actuel    = $licencieRepository->countActifsBySaison($saisonActive);
        $precedent = $licencieRepository->countActifsBySaison($saisonPrecedente);

        if (0 === $precedent) {
            return null;
        }

        $variation = round((($actuel - $precedent) / $precedent) * 100, 1);

        return [
            'value'    => sprintf('%+g%%', $variation),
            'positive' => $variation >= 0,
            'previous' => (string) $saisonPrecedente->getLibelle(),
        ];
    }
}

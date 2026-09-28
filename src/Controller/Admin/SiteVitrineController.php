<?php

namespace App\Controller\Admin;

use App\Entity\Membre;
use App\Repository\CategorieRepository;
use App\Repository\ContactSettingsRepository;
use App\Repository\HomepageBannerRepository;
use App\Repository\MatchLiveRepository;
use App\Repository\MembreRepository;
use App\Repository\OffreEmploiRepository;
use App\Repository\PageContenuRepository;
use App\Repository\PartenaireRepository;
use App\Repository\RejoindreCardRepository;
use App\Repository\SlideCarouselRepository;
use App\Service\SiteAdvisor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Point d'entrée unique de la gestion du site vitrine : un tableau de bord
 * avec une carte (chiffres + graphique) par rubrique. Cliquer sur une carte
 * ouvre la gestion de la rubrique dans une grande fenêtre (les pages CRUD
 * existantes, affichées en mode « intégré »).
 */
#[Route('/admin/site-vitrine', name: 'admin_site_vitrine', methods: ['GET'])]
class SiteVitrineController extends AbstractController
{
    private const DAYS = [
        'horaireLundi'    => 'L',
        'horaireMardi'    => 'M',
        'horaireMercredi' => 'M',
        'horaireJeudi'    => 'J',
        'horaireVendredi' => 'V',
        'horaireSamedi'   => 'S',
        'horaireDimanche' => 'D',
    ];

    public function __invoke(
        Request $request,
        SiteAdvisor $advisor,
        PartenaireRepository $partenaires,
        RejoindreCardRepository $cards,
        OffreEmploiRepository $offres,
        SlideCarouselRepository $slides,
        PageContenuRepository $pages,
        MembreRepository $membres,
        CategorieRepository $categories,
        HomepageBannerRepository $banners,
        ContactSettingsRepository $contacts,
        MatchLiveRepository $matchs,
    ): Response {
        $sections = [];

        // --- Contenu -------------------------------------------------------
        $partenairesActifs = $partenaires->count(['statut' => 'active']);
        $sections[] = [
            'key'    => 'partenaires',
            'group'  => 'Contenu',
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
        ];

        $sections[] = $this->split('rejoindre', 'fa-user-plus', 'Nous rejoindre', 'admin_rejoindre_card_index', 'admin_rejoindre_card_new',
            $cards->count(['actif' => true]), $cards->count(['actif' => false]));

        $sections[] = $this->split('offres', 'fa-briefcase', "Offres d'emploi", 'admin_offre_emploi_index', 'admin_offre_emploi_new',
            $offres->count(['actif' => true]), $offres->count(['actif' => false]));

        $sections[] = $this->split('carousel', 'fa-images', 'Carousel', 'admin_slide_carousel_index', 'admin_slide_carousel_new',
            $slides->count(['actif' => true]), $slides->count(['actif' => false]));

        $derniere = $pages->findBy([], ['updatedAt' => 'DESC', 'id' => 'DESC'], 3);
        $sections[] = [
            'key'    => 'pages',
            'group'  => 'Contenu',
            'icon'   => 'fa-file-lines',
            'title'  => 'Pages',
            'url'    => $this->generateUrl('admin_page_contenu_index'),
            'new'    => $this->generateUrl('admin_page_contenu_new'),
            'value'  => $pages->count([]),
            'label'  => 'pages',
            'sub'    => 'dernières modifiées :',
            'visual' => ['type' => 'list', 'items' => array_map(
                static fn ($p) => ['title' => $p->getTitre(), 'meta' => $p->getUpdatedAt()?->format('d/m/Y') ?? '—'],
                $derniere,
            )],
        ];

        $sections[] = $this->encadrement($membres, $categories);

        // --- Réglages du site ---------------------------------------------
        $banner = $banners->getSingleton();
        $sections[] = [
            'key'    => 'accueil',
            'group'  => 'Réglages',
            'icon'   => 'fa-image',
            'title'  => "Bannière d'accueil",
            'url'    => $this->generateUrl('admin_reglages_accueil'),
            'new'    => null,
            'value'  => null,
            'visual' => [
                'type'   => 'status',
                'on'     => (bool) $banner?->isActif(),
                'onLabel'  => 'Affichée',
                'offLabel' => 'Masquée',
                'detail' => $banner?->getTitre() ?: ($banner?->getImageName() ? 'Image sans titre' : 'Aucune bannière configurée'),
            ],
        ];

        $contact = $contacts->getSingleton();
        $days = [];
        foreach (self::DAYS as $property => $letter) {
            $days[] = ['letter' => $letter, 'set' => '' !== trim((string) $contact?->{'get'.ucfirst($property)}())];
        }
        $sections[] = [
            'key'    => 'contact',
            'group'  => 'Réglages',
            'icon'   => 'fa-envelope',
            'title'  => 'Contact & horaires',
            'url'    => $this->generateUrl('admin_reglages_contact'),
            'new'    => null,
            'value'  => count(array_filter($days, static fn ($d) => $d['set'])),
            'label'  => 'jours renseignés',
            'sub'    => $contact && '' !== $contact->getEmail() ? $contact->getEmail() : 'Aucun email de contact',
            'visual' => ['type' => 'days', 'days' => $days],
        ];

        $match = $matchs->getSingleton();
        $sections[] = [
            'key'    => 'match_live',
            'group'  => 'Réglages',
            'icon'   => 'fa-tower-broadcast',
            'title'  => 'Match en live',
            'url'    => $this->generateUrl('admin_reglages_match_live'),
            'new'    => null,
            'value'  => null,
            'visual' => [
                'type'     => 'status',
                'on'       => (bool) $match?->isEnLigne(),
                'onLabel'  => 'En direct',
                'offLabel' => 'Hors ligne',
                'detail'   => $match?->getUrl() ?: 'Aucun lien de diffusion',
            ],
        ];

        return $this->render('admin/site_vitrine.html.twig', [
            'sections' => $sections,
            'advice'   => $advisor->items(SiteAdvisor::HUB_VITRINE),
            // ?ouvrir=contact[&vue=new] : ouvre directement la fenêtre d'une rubrique (liens des conseils, de la cloche...)
            'open'     => (string) $request->query->get('ouvrir', ''),
            'openView' => 'new' === $request->query->get('vue') ? 'new' : 'list',
        ]);
    }

    /** Carte « actifs / inactifs » avec barre de répartition. */
    private function split(string $key, string $icon, string $title, string $indexRoute, string $newRoute, int $active, int $inactive): array
    {
        return [
            'key'    => $key,
            'group'  => 'Contenu',
            'icon'   => $icon,
            'title'  => $title,
            'url'    => $this->generateUrl($indexRoute),
            'new'    => $this->generateUrl($newRoute),
            'value'  => $active,
            'label'  => 'actives',
            'sub'    => sprintf('sur %d au total', $active + $inactive),
            'visual' => ['type' => 'split', 'active' => $active, 'inactive' => $inactive],
        ];
    }

    private function encadrement(MembreRepository $membres, CategorieRepository $categories): array
    {
        $items = [];
        foreach ($categories->findBy([], ['ordre' => 'ASC']) as $categorie) {
            $count = $categorie->getMembres()->filter(static fn (Membre $m) => true === $m->isActif())->count();
            $items[] = ['label' => $categorie->getNom(), 'value' => $count];
        }
        usort($items, static fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'key'    => 'encadrement',
            'group'  => 'Contenu',
            'icon'   => 'fa-people-group',
            'title'  => 'Encadrement',
            'url'    => $this->generateUrl('admin_membre_index'),
            'new'    => $this->generateUrl('admin_membre_new'),
            'value'  => $membres->count(['actif' => true]),
            'label'  => 'membres actifs',
            'sub'    => 'par catégorie :',
            'visual' => ['type' => 'bars', 'items' => array_slice($items, 0, 5), 'max' => max(1, ...array_column($items ?: [['value' => 1]], 'value'))],
        ];
    }
}

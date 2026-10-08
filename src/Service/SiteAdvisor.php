<?php

namespace App\Service;

use App\Entity\Membre;
use App\Repository\CategorieRepository;
use App\Repository\ContactSettingsRepository;
use App\Repository\EquipeRepository;
use App\Repository\FamilleRepository;
use App\Repository\HomepageBannerRepository;
use App\Repository\LicencieRepository;
use App\Repository\MembreRepository;
use App\Repository\PartenaireRepository;
use App\Repository\RejoindreCardRepository;
use App\Repository\SaisonRepository;
use App\Repository\SlideCarouselRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * « Conseils de configuration » : liste ce qu'il reste à alimenter pour que le
 * site vitrine et la gestion des licenciés soient complets. Purement
 * indicatif : rien n'est bloqué, l'administrateur est simplement invité à
 * compléter (carte « À compléter », pastille du menu, cloche, tableau de bord).
 *
 * Chaque conseil : hub (vitrine|licencies), section (clé de la carte du hub),
 * icon, label, detail, target (list|new : ce que la fenêtre ouvre en premier),
 * optional (simple suggestion).
 */
class SiteAdvisor
{
    public const HUB_VITRINE    = 'vitrine';
    public const HUB_LICENCIES  = 'licencies';
    public const HUB_PARTENAIRE = 'partenaire';

    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    public function __construct(
        private readonly Security $security,
        private readonly PartenaireRepository $partenaires,
        private readonly RejoindreCardRepository $cards,
        private readonly SlideCarouselRepository $slides,
        private readonly MembreRepository $membres,
        private readonly CategorieRepository $categories,
        private readonly HomepageBannerRepository $banners,
        private readonly ContactSettingsRepository $contacts,
        private readonly SaisonRepository $saisons,
        private readonly EquipeRepository $equipes,
        private readonly FamilleRepository $familles,
        private readonly LicencieRepository $licencies,
    ) {
    }

    /** Nombre de conseils d'un hub (0 pour qui n'est pas administrateur). */
    public function count(string $hub): int
    {
        return count($this->items($hub));
    }

    /** @return list<array<string, mixed>> */
    public function items(string $hub): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        return $this->cache[$hub] ??= match ($hub) {
            self::HUB_VITRINE    => $this->vitrine(),
            self::HUB_LICENCIES  => $this->licenciesHub(),
            self::HUB_PARTENAIRE => $this->partenaireHub(),
            default              => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function vitrine(): array
    {
        $items = [];

        $contact = $this->contacts->getSingleton();
        $manquants = [];
        if (!$contact || '' === trim($contact->getEmail())) {
            $manquants[] = 'email';
        }
        if (!$contact || '' === trim($contact->getAdresse())) {
            $manquants[] = 'adresse';
        }
        if (!$contact || '' === trim($contact->getTelephone())) {
            $manquants[] = 'téléphone';
        }
        $jours = 0;
        if ($contact) {
            foreach (['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'] as $jour) {
                if ('' !== trim((string) $contact->{'getHoraire'.$jour}())) {
                    ++$jours;
                }
            }
        }
        if ($jours < 7) {
            $manquants[] = sprintf('horaires (%d jour%s sur 7)', $jours, $jours > 1 ? 's' : '');
        }
        if ([] !== $manquants) {
            $items[] = $this->item(
                self::HUB_VITRINE,
                'contact',
                'fa-envelope',
                'Compléter les informations de contact',
                'Manque : '.implode(', ', $manquants).'. Affiché sur la page Contact du site.',
            );
        }

        if (0 === $this->cards->count(['actif' => true])) {
            $items[] = $this->item(
                self::HUB_VITRINE,
                'rejoindre',
                'fa-user-plus',
                'Créer une carte « Nous rejoindre »',
                'Aucune carte active : cette rubrique de l\'accueil est vide.',
                'new',
            );
        }

        if (0 === $this->slides->count(['actif' => true])) {
            $items[] = $this->item(
                self::HUB_VITRINE,
                'carousel',
                'fa-images',
                'Ajouter des slides au carousel',
                "Aucune slide active : le carousel de la page d'accueil est vide.",
                'new',
            );
        }

        $membresActifs = $this->membres->count(['actif' => true]);
        if (0 === $membresActifs) {
            $items[] = $this->item(
                self::HUB_VITRINE,
                'encadrement',
                'fa-people-group',
                "Présenter l'encadrement",
                'Aucun membre actif : la page Encadrement est vide.',
                'new',
            );
        } else {
            $vides = [];
            foreach ($this->categories->findBy([], ['ordre' => 'ASC']) as $categorie) {
                if ($categorie->getMembres()->filter(static fn (Membre $m) => true === $m->isActif())->isEmpty()) {
                    $vides[] = $categorie->getNom();
                }
            }
            if ([] !== $vides) {
                $items[] = $this->item(
                    self::HUB_VITRINE,
                    'encadrement',
                    'fa-people-group',
                    'Catégories sans encadrant',
                    'Aucun membre actif dans : '.implode(', ', $vides).'.',
                    'new',
                    true,
                );
            }
        }

        $banner = $this->banners->getSingleton();
        if (!$banner || !$banner->getImageName()) {
            $items[] = $this->item(
                self::HUB_VITRINE,
                'accueil',
                'fa-image',
                "Ajouter une bannière d'accueil",
                'Optionnel : utile pour mettre en avant un match ou un événement.',
                'list',
                true,
            );
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function partenaireHub(): array
    {
        $items = [];

        if (0 === $this->partenaires->count(['statut' => 'active'])) {
            $items[] = $this->item(
                self::HUB_PARTENAIRE,
                'partenaires',
                'fa-handshake',
                'Ajouter vos partenaires',
                'Aucun partenaire actif : la bande de sponsors du site est vide.',
                'new',
            );
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function licenciesHub(): array
    {
        $items = [];

        if (null === $this->saisons->findActive()) {
            $items[] = $this->item(
                self::HUB_LICENCIES,
                'saison',
                'fa-calendar-days',
                'Définir la saison en cours',
                'Aucune saison n\'est marquée « en cours » : les statistiques de licenciés en dépendent.',
                0 === $this->saisons->count([]) ? 'new' : 'list',
            );
        }

        if (0 === $this->equipes->count(['statut' => 'active'])) {
            $items[] = $this->item(
                self::HUB_LICENCIES,
                'equipe',
                'fa-shirt',
                'Créer vos équipes',
                'Aucune équipe active : les licenciés ne peuvent pas être affiliés pour les matchs.',
                'new',
            );
        }

        $sansLicencie = (int) $this->familles->createQueryBuilder('f')
            ->select('COUNT(f.id)')->andWhere('f.licencies IS EMPTY')
            ->getQuery()->getSingleScalarResult();
        if ($sansLicencie > 0) {
            $items[] = $this->item(
                self::HUB_LICENCIES,
                'famille',
                'fa-house-user',
                'Familles sans licencié',
                sprintf('%d famille%s ne compte%s aucun licencié.', $sansLicencie, $sansLicencie > 1 ? 's' : '', $sansLicencie > 1 ? 'nt' : ''),
                'list',
                true,
            );
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function item(string $hub, string $section, string $icon, string $label, string $detail, string $target = 'list', bool $optional = false): array
    {
        return compact('hub', 'section', 'icon', 'label', 'detail', 'target', 'optional');
    }
}

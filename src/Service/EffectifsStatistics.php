<?php

namespace App\Service;

use App\Entity\Licencie;
use App\Entity\Saison;
use App\Repository\AdhesionRepository;
use App\Repository\LicencieRepository;
use App\Repository\SaisonRepository;

/**
 * Statistiques d'effectifs d'une saison, comparées à la précédente : licenciés, répartition par catégorie d'âge,
 * taux de réinscription (licenciés de la saison précédente qui sont aussi de celle-ci), nouveaux et partants.
 *
 * Un licencié n'a qu'une fiche, qui change de saison au fil des ans : l'historique vient des licences
 * (une par licencié et par saison). Sont comptés pour une saison les licenciés actifs rattachés à cette saison et
 * ceux qui ont une licence enregistrée pour elle. La catégorie est celle que le licencié avait cette saison-là.
 */
final class EffectifsStatistics
{
    public function __construct(
        private readonly LicencieRepository $licencies,
        private readonly AdhesionRepository $adhesions,
        private readonly SaisonRepository $saisons,
    ) {
    }

    /**
     * @return array{
     *     saison: ?Saison, precedente: ?Saison, total: int, totalPrecedent: int, evolution: ?float,
     *     categories: list<array{categorie: string, actuel: int, precedent: int, ecart: int}>,
     *     reinscrits: int, nouveaux: int, partants: int, tauxReinscription: ?float,
     * }
     */
    public function pour(?Saison $saison): array
    {
        $precedente = $saison ? $this->saisons->findPrevious($saison) : null;
        $actuels    = $saison ? $this->effectif($saison) : [];
        $anciens    = $precedente ? $this->effectif($precedente) : [];

        $categories = [];
        foreach ($actuels as $licencie) {
            $c = $this->categorie($licencie, $saison);
            $categories[$c]['actuel'] = ($categories[$c]['actuel'] ?? 0) + 1;
        }
        foreach ($anciens as $licencie) {
            $c = $this->categorie($licencie, $precedente);
            $categories[$c]['precedent'] = ($categories[$c]['precedent'] ?? 0) + 1;
        }
        $lignes = [];
        foreach ($categories as $categorie => $counts) {
            $actuel    = $counts['actuel'] ?? 0;
            $precedent = $counts['precedent'] ?? 0;
            $lignes[]  = ['categorie' => (string) $categorie, 'actuel' => $actuel, 'precedent' => $precedent, 'ecart' => $actuel - $precedent];
        }
        usort($lignes, static fn (array $a, array $b): int => self::order($a['categorie']) <=> self::order($b['categorie']));

        $idsActuels = array_keys($actuels);
        $idsAnciens = array_keys($anciens);
        $reinscrits = count(array_intersect($idsAnciens, $idsActuels));

        $total          = count($actuels);
        $totalPrecedent = count($anciens);

        return [
            'saison'            => $saison,
            'precedente'        => $precedente,
            'total'             => $total,
            'totalPrecedent'    => $totalPrecedent,
            'evolution'         => $totalPrecedent > 0 ? round(($total - $totalPrecedent) / $totalPrecedent * 100, 1) : null,
            'categories'        => $lignes,
            'reinscrits'        => $reinscrits,
            'nouveaux'          => count(array_diff($idsActuels, $idsAnciens)),
            'partants'          => count(array_diff($idsAnciens, $idsActuels)),
            'tauxReinscription' => $totalPrecedent > 0 ? round($reinscrits / $totalPrecedent * 100, 1) : null,
        ];
    }

    /**
     * Licenciés d'une saison, indexés par identifiant.
     *
     * @return array<int, Licencie>
     */
    private function effectif(Saison $saison): array
    {
        $effectif = [];
        foreach ($this->licencies->findBy(['saison' => $saison]) as $licencie) {
            if (Licencie::STATUT_ACTIVE === $licencie->getStatut()) {
                $effectif[(int) $licencie->getId()] = $licencie;
            }
        }
        foreach ($this->adhesions->findBy(['saison' => $saison]) as $adhesion) {
            $licencie = $adhesion->getLicencie();
            if (null !== $licencie) {
                $effectif[(int) $licencie->getId()] = $licencie;
            }
        }

        return $effectif;
    }

    private function categorie(Licencie $licencie, ?Saison $saison): string
    {
        $naissance = $licencie->getDateNaissance();

        return $naissance ? CategorieAge::calculer($naissance, $saison, $licencie->getDecalageCategorie()) : '—';
    }

    /** Ordre d'affichage : U6 … U19, puis Senior, puis le reste. */
    private static function order(string $categorie): int
    {
        if (CategorieAge::SENIOR === $categorie) {
            return 100;
        }

        return 1 === preg_match('/^U(\d+)$/', $categorie, $m) ? (int) $m[1] : 1000;
    }
}

<?php

namespace App\Service\Dashboard;

use App\Entity\Adhesion;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\ContratPartenaire;
use App\Entity\Entrainement;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Partenaire;
use App\Entity\Reglement;
use App\Entity\Saison;
use App\Repository\AdhesionRepository;
use App\Repository\ContratPartenaireRepository;
use App\Repository\EntrainementRepository;
use App\Repository\SaisonRepository;
use App\Service\EffectifsStatistics;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Chiffres et séries du tableau de bord de l'administration. Chaque méthode ne lit que ce dont son graphique a besoin
 * (agrégats ou une seule colonne) et renvoie des tableaux simples : les libellés sont traduits par le gabarit, jamais ici.
 *
 * Les séries mensuelles couvrent toujours N mois pleins (mois courant inclus), y compris les mois sans donnée.
 */
class DashboardStatistics
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SaisonRepository $saisons,
        private readonly AdhesionRepository $adhesions,
        private readonly ContratPartenaireRepository $contrats,
        private readonly EntrainementRepository $entrainements,
        private readonly EffectifsStatistics $effectifs,
    ) {
    }

    // ------------------------------------------------------------ licenciés

    /**
     * Répartition par catégorie d'âge de la saison active, comparée à la précédente.
     *
     * @return array{saison: ?string, total: int, evolution: ?float, tauxReinscription: ?float, nouveaux: int, partants: int, categories: list<array{categorie: string, actuel: int, precedent: int}>}
     */
    public function effectifs(): array
    {
        $saison = $this->saisons->findActive();
        $stats  = $this->effectifs->pour($saison);

        return [
            'saison'            => $saison?->getLibelle(),
            'total'             => $stats['total'],
            'evolution'         => $stats['evolution'],
            'tauxReinscription' => $stats['tauxReinscription'],
            'nouveaux'          => $stats['nouveaux'],
            'partants'          => $stats['partants'],
            'categories'        => array_map(static fn (array $c): array => ['categorie' => $c['categorie'], 'actuel' => $c['actuel'], 'precedent' => $c['precedent']], $stats['categories']),
        ];
    }

    /**
     * Licenciés actifs : filles, garçons, non renseigné.
     *
     * @return array{female: int, male: int, unknown: int}
     */
    public function sexes(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('l.sexe AS sexe', 'COUNT(l.id) AS n')
            ->from(Licencie::class, 'l')
            ->andWhere('l.actif = true')
            ->groupBy('l.sexe')
            ->getQuery()->getArrayResult();

        $out = ['female' => 0, 'male' => 0, 'unknown' => 0];
        foreach ($rows as $row) {
            $sexe = strtoupper(substr(trim((string) $row['sexe']), 0, 1));
            $out['F' === $sexe ? 'female' : ('M' === $sexe ? 'male' : 'unknown')] += (int) $row['n'];
        }

        return $out;
    }

    /**
     * Licenciés actifs par saison, des plus anciennes aux plus récentes.
     *
     * @return list<array{label: string, value: int}>
     */
    public function licenciesParSaison(int $max = 6): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('s.libelle AS libelle', 'COUNT(l.id) AS n')
            ->from(Licencie::class, 'l')
            ->join('l.saison', 's')
            ->andWhere('l.actif = true')
            ->groupBy('s.id', 's.libelle', 's.dateDebut')
            ->orderBy('s.dateDebut', 'DESC')
            ->setMaxResults($max)
            ->getQuery()->getArrayResult();

        return array_values(array_reverse(array_map(static fn (array $r): array => ['label' => (string) $r['libelle'], 'value' => (int) $r['n']], $rows)));
    }

    /**
     * Licenciés actifs par équipe (les plus fournies d'abord).
     *
     * @return list<array{label: string, value: int}>
     */
    public function licenciesParEquipe(int $max = 8): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('e.nom AS nom', 'COUNT(l.id) AS n')
            ->from(Licencie::class, 'l')
            ->join('l.equipes', 'e')
            ->andWhere('l.actif = true')
            ->andWhere('e.actif = true')
            ->groupBy('e.id', 'e.nom')
            ->orderBy('n', 'DESC')
            ->setMaxResults($max)
            ->getQuery()->getArrayResult();

        return array_values(array_map(static fn (array $r): array => ['label' => (string) $r['nom'], 'value' => (int) $r['n']], $rows));
    }

    /**
     * Familles selon leur nombre de licenciés : 0, 1, 2, 3 et plus.
     *
     * @return array{empty: int, one: int, two: int, three: int}
     */
    public function taillesFamilles(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('COUNT(l.id) AS n')
            ->from(Famille::class, 'f')
            ->leftJoin('f.licencies', 'l', 'WITH', 'l.actif = true')
            ->groupBy('f.id')
            ->getQuery()->getSingleColumnResult();

        $out = ['empty' => 0, 'one' => 0, 'two' => 0, 'three' => 0];
        foreach ($rows as $n) {
            ++$out[match (true) {
                $n <= 0 => 'empty', 1 === (int) $n => 'one', 2 === (int) $n => 'two', default => 'three'
            }];
        }

        return $out;
    }

    /**
     * Nouvelles familles par mois.
     *
     * @return list<array{month: \DateTimeImmutable, value: int}>
     */
    public function famillesParMois(int $months = 12): array
    {
        return $this->countByMonth(Famille::class, 'createdAt', $months);
    }

    // ------------------------------------------------- licences et paiements

    /**
     * Situation financière des licences de la saison active : dû, encaissé, aides attendues, reste, et nombre de dossiers par statut.
     *
     * @return array{saison: ?string, due: int, received: int, aids: int, remaining: int, count: int, statuses: array<string, int>}
     */
    public function paiements(): array
    {
        $saison = $this->saisons->findActive();
        $out    = ['saison' => $saison?->getLibelle(), 'due' => 0, 'received' => 0, 'aids' => 0, 'remaining' => 0, 'count' => 0, 'statuses' => []];
        if (!$saison instanceof Saison) {
            return $out;
        }

        /** @var list<Adhesion> $adhesions */
        $adhesions = $this->adhesions->findAllDetailed($saison);
        foreach ($adhesions as $adhesion) {
            ++$out['count'];
            $out['due']       += $adhesion->getMontantDuCentimes();
            $out['received']  += $adhesion->getRecuCentimes();
            $out['aids']      += $adhesion->getAidesAttenduesCentimes();
            $out['remaining'] += $adhesion->getResteCentimes();
            $status = $adhesion->getStatutPaiement();
            $out['statuses'][$status] = ($out['statuses'][$status] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * Règlements de licences encaissés par mois (en centimes).
     *
     * @return list<array{month: \DateTimeImmutable, value: int}>
     */
    public function reglementsParMois(int $months = 12): array
    {
        $start = $this->startOfSeries($months);
        $rows  = $this->em->createQueryBuilder()
            ->select('r.dateRemise AS d', 'r.dateEcheance AS e', 'r.montantCentimes AS m')
            ->from(Reglement::class, 'r')
            ->andWhere('r.recu = true')
            ->getQuery()->getArrayResult();

        $byMonth = [];
        foreach ($rows as $row) {
            $date = $row['d'] ?? $row['e'];
            if ($date instanceof \DateTimeInterface && $date >= $start) {
                $key = $date->format('Y-m');
                $byMonth[$key] = ($byMonth[$key] ?? 0) + (int) $row['m'];
            }
        }

        return $this->fillMonths($start, $months, $byMonth);
    }

    /**
     * Règlements encaissés par mode de paiement (en centimes).
     *
     * @return array<string, int>
     */
    public function modesReglement(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('r.mode AS mode', 'SUM(r.montantCentimes) AS total')
            ->from(Reglement::class, 'r')
            ->andWhere('r.recu = true')
            ->groupBy('r.mode')
            ->getQuery()->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['mode']] = (int) $row['total'];
        }

        return $out;
    }

    // -------------------------------------------------------------- boutique

    /**
     * Commandes par statut.
     *
     * @return array<string, int>
     */
    public function commandesParStatut(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('c.statut AS statut', 'COUNT(c.id) AS n')
            ->from(Commande::class, 'c')
            ->groupBy('c.statut')
            ->getQuery()->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['statut']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Commandes passées par mois (hors annulées).
     *
     * @return list<array{month: \DateTimeImmutable, value: int}>
     */
    public function commandesParMois(int $months = 12): array
    {
        $start = $this->startOfSeries($months);
        $dates = $this->em->createQueryBuilder()
            ->select('c.createdAt')
            ->from(Commande::class, 'c')
            ->andWhere('c.statut != :cancelled')->setParameter('cancelled', Commande::STATUT_ANNULEE)
            ->getQuery()->getSingleColumnResult();

        $byMonth = [];
        foreach ($dates as $date) {
            $date = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable((string) $date);
            if ($date >= $start) {
                $key = $date->format('Y-m');
                $byMonth[$key] = ($byMonth[$key] ?? 0) + 1;
            }
        }

        return $this->fillMonths($start, $months, $byMonth);
    }

    /**
     * Articles les plus vendus (quantités sur les commandes non annulées).
     *
     * @return list<array{label: string, value: int}>
     */
    public function topArticles(int $max = 6): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('l.articleNom AS nom', 'SUM(l.quantite) AS n')
            ->from(CommandeLigne::class, 'l')
            ->join('l.commande', 'c')
            ->andWhere('c.statut != :cancelled')->setParameter('cancelled', Commande::STATUT_ANNULEE)
            ->groupBy('l.articleNom')
            ->orderBy('n', 'DESC')
            ->setMaxResults($max)
            ->getQuery()->getArrayResult();

        return array_values(array_map(static fn (array $r): array => ['label' => (string) $r['nom'], 'value' => (int) $r['n']], $rows));
    }

    /**
     * Indicateurs de la boutique : commandes à régler, panier moyen, chiffre d'affaires du mois et nombre de commandes payées.
     *
     * @return array{pending: int, average: int, monthRevenue: int, paid: int}
     */
    public function boutique(): array
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $paid       = $this->em->createQueryBuilder()
            ->select('COUNT(c.id) AS n', 'COALESCE(SUM(c.totalCentimes), 0) AS total')
            ->from(Commande::class, 'c')
            ->andWhere('c.reglement = :paid')->setParameter('paid', Commande::REGLEMENT_PAYE)
            ->andWhere('c.statut != :cancelled')->setParameter('cancelled', Commande::STATUT_ANNULEE)
            ->getQuery()->getSingleResult();
        $month = (int) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(c.totalCentimes), 0)')
            ->from(Commande::class, 'c')
            ->andWhere('c.reglement = :paid')->setParameter('paid', Commande::REGLEMENT_PAYE)
            ->andWhere('c.statut != :cancelled')->setParameter('cancelled', Commande::STATUT_ANNULEE)
            ->andWhere('c.createdAt >= :start')->setParameter('start', $monthStart)
            ->getQuery()->getSingleScalarResult();
        $pending = (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Commande::class, 'c')
            ->andWhere('c.reglement = :wait')->setParameter('wait', Commande::REGLEMENT_ATTENTE)
            ->andWhere('c.statut != :cancelled')->setParameter('cancelled', Commande::STATUT_ANNULEE)
            ->getQuery()->getSingleScalarResult();

        $count = (int) $paid['n'];

        return ['pending' => $pending, 'average' => $count > 0 ? intdiv((int) $paid['total'], $count) : 0, 'monthRevenue' => $month, 'paid' => $count];
    }

    // ------------------------------------------------------------ partenaires

    /**
     * Partenaires actifs par catégorie.
     *
     * @return list<array{label: ?string, value: int}>
     */
    public function partenairesParCategorie(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('c.nom AS nom', 'COUNT(p.id) AS n')
            ->from(Partenaire::class, 'p')
            ->leftJoin('p.categorie', 'c')
            ->andWhere('p.actif = true')
            ->groupBy('c.id', 'c.nom')
            ->orderBy('n', 'DESC')
            ->getQuery()->getArrayResult();

        return array_values(array_map(static fn (array $r): array => ['label' => $r['nom'] ? (string) $r['nom'] : null, 'value' => (int) $r['n']], $rows));
    }

    /**
     * Contrats de partenariat : total contractualisé, encaissé et reste, plus le nombre de contrats en cours.
     *
     * @return array{total: int, received: int, remaining: int, active: int}
     */
    public function contrats(): array
    {
        $total     = $this->contrats->totalMontantCentimes();
        $remaining = $this->contrats->findAvecResteAPayer(1, 1)['totalResteCentimes'];
        $today     = new \DateTimeImmutable('today');
        $active    = (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(ContratPartenaire::class, 'c')
            ->andWhere('c.dateFin IS NULL OR c.dateFin >= :today')->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();

        return ['total' => $total, 'received' => max(0, $total - $remaining), 'remaining' => $remaining, 'active' => $active];
    }

    /**
     * Tâches de contrat à traiter (retard, cette semaine) et les prochaines à échéance.
     *
     * @return array{late: int, soon: int, next: list<\App\Entity\ContratPartenaireTache>}
     */
    public function taches(): array
    {
        return $this->contrats->tachesATraiter() + ['next' => $this->contrats->tachesEnAttente(1, 5)['rows']];
    }

    // --------------------------------------------------------------- planning

    /**
     * Prochains événements du planning (7 jours).
     *
     * @return list<Entrainement>
     */
    public function prochainsEvenements(int $max = 6): array
    {
        return array_slice($this->entrainements->between(new \DateTimeImmutable('today'), new \DateTimeImmutable('+7 days')), 0, $max);
    }

    /**
     * Répartition des événements du mois : entraînements, rencontres, autres.
     *
     * @return array{training: int, match: int, event: int}
     */
    public function typesEvenements(): array
    {
        $out = ['training' => 0, 'match' => 0, 'event' => 0];
        foreach ($this->entrainements->between(new \DateTimeImmutable('first day of this month midnight'), new \DateTimeImmutable('last day of this month 23:59:59')) as $event) {
            ++$out[$event->isEvenement() ? 'event' : ($event->isRencontre() ? 'match' : 'training')];
        }

        return $out;
    }

    // ---------------------------------------------------------------- outils

    private function startOfSeries(int $months): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('first day of this month midnight'))->modify(sprintf('-%d months', $months - 1));
    }

    /**
     * @param array<string, int> $byMonth
     *
     * @return list<array{month: \DateTimeImmutable, value: int}>
     */
    private function fillMonths(\DateTimeImmutable $start, int $months, array $byMonth): array
    {
        $series = [];
        for ($i = 0; $i < $months; ++$i) {
            $month    = $start->modify(sprintf('+%d months', $i));
            $series[] = ['month' => $month, 'value' => $byMonth[$month->format('Y-m')] ?? 0];
        }

        return $series;
    }

    /**
     * @param class-string $class
     *
     * @return list<array{month: \DateTimeImmutable, value: int}>
     */
    private function countByMonth(string $class, string $field, int $months): array
    {
        $start = $this->startOfSeries($months);
        $dates = $this->em->createQueryBuilder()
            ->select('e.'.$field)
            ->from($class, 'e')
            ->andWhere('e.'.$field.' >= :start')->setParameter('start', $start)
            ->getQuery()->getSingleColumnResult();

        $byMonth = [];
        foreach ($dates as $date) {
            $date = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable((string) $date);
            $key  = $date->format('Y-m');
            $byMonth[$key] = ($byMonth[$key] ?? 0) + 1;
        }

        return $this->fillMonths($start, $months, $byMonth);
    }
}

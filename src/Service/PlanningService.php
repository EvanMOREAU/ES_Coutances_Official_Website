<?php

namespace App\Service;

use App\Entity\Entrainement;
use App\Entity\Equipe;
use App\Entity\Licencie;
use App\Entity\User;
use App\Repository\EquipeRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;

/**
 * Logique du planning : mise en forme JSON des séances, validation des
 * données envoyées par le calendrier, et qui voit quoi (catégories, équipes).
 */
class PlanningService
{
    /** Nombre maximal de séances créées d'un coup par une répétition hebdomadaire. */
    public const MAX_OCCURRENCES = 60;

    /** @var array<int, Equipe>|null équipes indexées par id */
    private ?array $equipes = null;

    public function __construct(
        private readonly FamilleRepository $familleRepository,
        private readonly LicencieRepository $licencieRepository,
        private readonly EquipeRepository $equipeRepository,
    ) {
    }

    /** @return array<int, Equipe> */
    private function equipes(): array
    {
        if (null === $this->equipes) {
            $this->equipes = [];
            foreach ($this->equipeRepository->findAll() as $equipe) {
                $this->equipes[$equipe->getId()] = $equipe;
            }
        }

        return $this->equipes;
    }

    /** Équipes actives proposées dans le formulaire du calendrier. @return list<array{id: int, nom: string, categorie: string}> */
    public function equipeChoices(): array
    {
        $choices = [];
        foreach ($this->equipeRepository->findBy(['statut' => 'active'], ['categorie' => 'ASC', 'nom' => 'ASC']) as $equipe) {
            $choices[] = ['id' => $equipe->getId(), 'nom' => (string) $equipe->getNom(), 'categorie' => (string) $equipe->getCategorie()];
        }

        return $choices;
    }

    /** @return array<string, mixed> */
    public function serialize(Entrainement $e): array
    {
        $equipes = [];
        foreach ($e->getEquipes() as $id) {
            if (isset($this->equipes()[$id])) {
                $equipe    = $this->equipes()[$id];
                $equipes[] = ['id' => $id, 'nom' => (string) $equipe->getNom(), 'categorie' => (string) $equipe->getCategorie()];
            }
        }

        return [
            'id'          => $e->getId(),
            'type'        => $e->getType(),
            'titre'       => $e->getTitre(),
            'categories'  => $e->getCategories(),
            'equipes'     => $equipes,
            'date'        => $e->getDate()?->format('Y-m-d'),
            'debut'       => $e->getHeureDebut()?->format('H:i'),
            'fin'         => $e->getHeureFin()?->format('H:i'),
            'lieu'        => $e->getLieu(),
            'description' => $e->getDescription(),
            'serie'       => null !== $e->getSerie(),
        ];
    }

    /**
     * Licenciés en cours (saison non terminée) rattachés à un compte : ceux de la
     * famille dont il est titulaire, et lui-même s'il est licencié.
     *
     * @return list<Licencie>
     */
    public function licenciesFor(User $user): array
    {
        $licencies = [];
        if ($famille = $this->familleRepository->findOneBy(['user' => $user])) {
            foreach ($famille->getLicencies() as $licencie) {
                $licencies[$licencie->getId()] = $licencie;
            }
        }
        if ($licencie = $this->licencieRepository->findOneBy(['user' => $user])) {
            $licencies[$licencie->getId()] = $licencie;
        }

        return array_values(array_filter($licencies, static fn (Licencie $l) => $l->isEnCours()));
    }

    /**
     * Catégories d'âge des licenciés en cours rattachés à un compte.
     *
     * @return list<string>
     */
    public function categoriesFor(User $user): array
    {
        $categories = [];
        foreach ($this->licenciesFor($user) as $licencie) {
            if ($licencie->getCategorie()) {
                $categories[] = $licencie->getCategorie();
            }
        }

        return array_values(array_unique($categories));
    }

    /**
     * Une séance concerne-t-elle l'un de ces licenciés ?
     *
     * Rencontre : les licenciés des deux équipes. Entraînement : les licenciés des catégories
     * visées ; si des équipes précises sont choisies dans une catégorie, seulement leurs membres.
     *
     * @param list<Licencie> $licencies
     */
    public function concerns(Entrainement $e, array $licencies): bool
    {
        foreach ($licencies as $licencie) {
            $mesEquipes = array_map(static fn (Equipe $eq) => $eq->getId(), $licencie->getEquipes()->toArray());

            if ($e->isRencontre()) {
                if ([] !== array_intersect($e->getEquipes(), $mesEquipes)) {
                    return true;
                }
                continue;
            }

            $categorie = $licencie->getCategorie();
            if (!in_array($categorie, $e->getCategories(), true)) {
                continue;
            }
            $visees = array_filter($e->getEquipes(), fn (int $id) => ($this->equipes()[$id] ?? null)?->getCategorie() === $categorie);
            if ([] === $visees || [] !== array_intersect($visees, $mesEquipes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valide les données d'une séance (formulaire du calendrier).
     *
     * @param array<string, mixed> $data
     * @return array{errors: array<string, string>, values: array<string, mixed>}
     */
    public function validate(array $data): array
    {
        $errors = [];

        $type = Entrainement::TYPE_RENCONTRE === ($data['type'] ?? null) ? Entrainement::TYPE_RENCONTRE : Entrainement::TYPE_ENTRAINEMENT;

        $titre = trim((string) ($data['titre'] ?? ''));
        if (mb_strlen($titre) > 150) {
            $errors['titre'] = 'Le titre est trop long (150 caractères maximum).';
        }

        $categories = [];
        $equipeIds  = array_values(array_unique(array_map('intval', array_filter((array) ($data['equipes'] ?? []), static fn ($v) => '' !== $v && null !== $v))));
        $equipes    = array_filter(array_map(fn (int $id) => $this->equipes()[$id] ?? null, $equipeIds));

        if (Entrainement::TYPE_RENCONTRE === $type) {
            if (2 !== count($equipes) || 2 !== count($equipeIds)) {
                $errors['equipes'] = 'Choisissez deux équipes différentes.';
            } else {
                $equipes    = array_values($equipes);
                $categories = array_values(array_unique(array_map(static fn (Equipe $eq) => (string) $eq->getCategorie(), $equipes)));
                if ('' === $titre) {
                    $titre = sprintf('%s – %s', $equipes[0]->getNom(), $equipes[1]->getNom());
                }
            }
        } else {
            $categories = array_values(array_unique(array_map('strval', (array) ($data['categories'] ?? []))));
            if ([] === $categories) {
                $errors['categories'] = 'Choisissez au moins une catégorie.';
            } elseif ([] !== array_diff($categories, CategorieAge::choices())) {
                $errors['categories'] = 'Catégorie inconnue.';
            }
            // Seules les équipes des catégories cochées sont retenues.
            $equipeIds = array_values(array_map(
                static fn (Equipe $eq) => $eq->getId(),
                array_filter($equipes, static fn (Equipe $eq) => in_array($eq->getCategorie(), $categories, true)),
            ));
        }

        $date = $this->parseDate($data['date'] ?? null);
        if (!$date) {
            $errors['date'] = 'Indiquez une date valide.';
        }

        $debut = $this->parseTime($data['debut'] ?? null);
        if (!$debut) {
            $errors['debut'] = 'Indiquez l\'heure de début.';
        }

        $fin = null;
        if ('' !== trim((string) ($data['fin'] ?? ''))) {
            $fin = $this->parseTime($data['fin']);
            if (!$fin) {
                $errors['fin'] = 'Heure de fin invalide.';
            } elseif ($debut && $fin <= $debut) {
                $errors['fin'] = 'La fin doit être après le début.';
            }
        }

        $lieu = trim((string) ($data['lieu'] ?? ''));
        if (mb_strlen($lieu) > 150) {
            $errors['lieu'] = 'Le lieu est trop long (150 caractères maximum).';
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 2000) {
            $errors['description'] = 'La description est trop longue (2 000 caractères maximum).';
        }

        $jusqua = null;
        if (!empty($data['repeter'])) {
            $jusqua = $this->parseDate($data['jusqua'] ?? null);
            if (!$jusqua) {
                $errors['jusqua'] = 'Indiquez jusqu\'à quelle date répéter la séance.';
            } elseif ($date && $jusqua < $date) {
                $errors['jusqua'] = 'Cette date doit être après la première séance.';
            } elseif ($date && (int) floor($date->diff($jusqua)->days / 7) + 1 > self::MAX_OCCURRENCES) {
                $errors['jusqua'] = sprintf('Une répétition est limitée à %d semaines.', self::MAX_OCCURRENCES);
            }
        }

        return [
            'errors' => $errors,
            'values' => [
                'type'        => $type,
                'titre'       => '' === $titre ? (Entrainement::TYPE_RENCONTRE === $type ? 'Rencontre' : 'Entraînement') : $titre,
                'categories'  => $categories,
                'equipes'     => $equipeIds,
                'date'        => $date,
                'debut'       => $debut,
                'fin'         => $fin,
                'lieu'        => '' === $lieu ? null : $lieu,
                'description' => '' === $description ? null : $description,
                'jusqua'      => $jusqua,
            ],
        ];
    }

    public function parseDate(mixed $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return $date && $date->format('Y-m-d') === (string) $value ? $date : null;
    }

    public function parseTime(mixed $value): ?\DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', (string) $value);

        return $time && $time->format('H:i') === (string) $value ? $time : null;
    }
}

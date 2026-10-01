<?php

namespace App\Security;

use App\Entity\ProfilAutorisation;
use App\Repository\ProfilAutorisationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Profils d'autorisation proposés au premier passage sur la page « Autorisations », pour ne pas
 * partir d'une feuille blanche. Ils se modifient et se suppriment comme les autres.
 */
class ProfilDefaults
{
    public function __construct(
        private readonly ProfilAutorisationRepository $profils,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Crée les profils de base si aucun n'existe encore. */
    public function seedIfEmpty(): void
    {
        if ($this->profils->count([]) > 0) {
            $this->completeFullProfile();

            return;
        }

        foreach (self::definitions() as $nom => [$description, $codes]) {
            $this->em->persist((new ProfilAutorisation())->setNom($nom)->setDescription($description)->setPermissions($codes));
        }
        $this->em->flush();
    }

    /** Le profil « Administrateur complet » reçoit les nouvelles autorisations ajoutées au catalogue depuis sa création. */
    private function completeFullProfile(): void
    {
        $full = $this->profils->findOneBy(['nom' => 'Administrateur complet']);
        if ($full && [] !== array_diff(PermissionCatalog::all(), $full->getPermissions())) {
            $full->setPermissions(PermissionCatalog::all());
            $this->em->flush();
        }
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    private static function definitions(): array
    {
        $all  = PermissionCatalog::all();
        $voir = array_values(array_filter($all, static fn (string $c) => str_ends_with($c, '.voir')));
        $crud = static fn (string $resource, array $actions = ['voir', 'creer', 'modifier', 'supprimer']): array => array_map(static fn (string $a) => $resource.'.'.$a, $actions);

        return [
            'Administrateur complet' => ['Toutes les autorisations, y compris la gestion des utilisateurs.', $all],
            'Secrétariat' => ['Gestion quotidienne des licenciés, des paiements, des imports et des commandes.', array_merge(
                $crud('famille', ['voir', 'creer', 'modifier']), $crud('licencie', ['voir', 'creer', 'modifier', 'renvoyer_acces', 'corriger_import']),
                $crud('equipe', ['voir', 'creer', 'modifier']), $crud('saison', ['voir']),
                $crud('adhesion', ['voir', 'creer', 'modifier', 'suivi']), $crud('import', ['voir', 'executer']),
                $crud('planning', ['voir']), $crud('commande', ['voir', 'traiter']), $crud('messagerie', ['utiliser']),
            )],
            'Éducateur' => ['Consulte les licenciés et gère le planning des entraînements.', array_merge(
                $crud('planning'), $crud('licencie', ['voir']), $crud('famille', ['voir']), $crud('equipe', ['voir']), $crud('messagerie', ['utiliser']),
            )],
            'Responsable boutique' => ['Articles, stock, commandes et bons de livraison.', array_merge($crud('article'), $crud('commande', ['voir', 'traiter', 'annuler']), $crud('code_promo'))],
            'Communication' => ['Site vitrine et fichiers.', array_merge(
                $crud('partenaire'), $crud('rejoindre_card'), $crud('offre_emploi'), $crud('slide_carousel'), $crud('page_contenu'), $crud('membre'), $crud('categorie'),
                $crud('reglages', ['voir', 'modifier']), $crud('fichiers', ['voir', 'televerser', 'modifier']),
            )],
            'Lecture seule' => ['Peut tout consulter, sans rien modifier.', $voir],
        ];
    }
}

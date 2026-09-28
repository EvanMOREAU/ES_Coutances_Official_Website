<?php

namespace App\Controller\Admin;

use App\Repository\ProfilAutorisationRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Security\ProfilDefaults;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page « Utilisateurs et autorisations » : une carte par rubrique (comptes du back-office,
 * profils d'autorisation), qui ouvre la gestion dans une grande fenêtre.
 */
#[Route('/admin/acces', name: 'admin_acces_hub', methods: ['GET'])]
class AccesHubController extends AbstractController
{
    public function __invoke(UserRepository $users, ProfilAutorisationRepository $profils, ProfilDefaults $defaults, PermissionChecker $permissions): Response
    {
        $defaults->seedIfEmpty();

        $staff = $users->createQueryBuilder('u')
            ->andWhere("u.roles LIKE '%ROLE_DEV%' OR u.roles LIKE '%ROLE_ADMIN%' OR u.roles LIKE '%ROLE_EDITOR%'")
            ->getQuery()->getResult();
        $parProfil = [];
        foreach ($staff as $user) {
            $key = $user->getProfil()?->getNom() ?? ($user->isAccesRestreint() ? 'Sur mesure' : 'Accès complet');
            $parProfil[$key] = ($parProfil[$key] ?? 0) + 1;
        }
        arsort($parProfil);
        $items = [];
        foreach (array_slice($parProfil, 0, 5, true) as $label => $count) {
            $items[] = ['label' => (string) $label, 'value' => $count];
        }

        $sections = [];
        if ($permissions->can('utilisateur.voir')) {
            $sections[] = [
                'key' => 'utilisateurs', 'group' => 'Accès', 'icon' => 'fa-users', 'title' => 'Utilisateurs',
                'url' => $this->generateUrl('admin_user_index'),
                'new' => $permissions->can('utilisateur.creer') ? $this->generateUrl('admin_user_new') : null,
                'value' => count($staff), 'label' => 'comptes du back-office', 'sub' => 'Développeurs, administrateurs, éditeurs',
                'visual' => ['type' => 'bars', 'caption' => 'Répartition par profil', 'items' => $items, 'max' => max(1, ...array_column($items ?: [['value' => 1]], 'value'))],
            ];
        }
        if ($permissions->can('autorisation.voir')) {
            $sections[] = [
                'key' => 'autorisations', 'group' => 'Accès', 'icon' => 'fa-user-shield', 'title' => 'Autorisations',
                'url' => $this->generateUrl('admin_profil_index'),
                'new' => $permissions->can('autorisation.creer') ? $this->generateUrl('admin_profil_new') : null,
                'value' => $profils->count([]), 'label' => 'profils', 'sub' => 'Ensembles d\'autorisations prêts à attribuer',
                'visual' => [
                    'type' => 'status', 'on' => true, 'onLabel' => 'Autorisation par autorisation', 'offLabel' => '',
                    'detail' => 'Chaque action du site (voir, créer, modifier, supprimer…) s\'accorde ou se retire séparément.',
                ],
            ];
        }

        return $this->render('admin/acces_hub.html.twig', ['sections' => $sections, 'open' => '', 'openView' => 'list']);
    }
}

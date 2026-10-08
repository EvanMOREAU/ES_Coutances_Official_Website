<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\PermissionCatalog;
use App\Security\PermissionChecker;
use App\Security\ProfilDefaults;
use App\Security\UserVoter;
use App\Service\AccountActivationMailer;
use Doctrine\ORM\EntityManagerInterface;
use SortDirection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/utilisateurs')]
class UserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $repository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $repository->createQueryBuilder('u')
                ->andWhere("u.roles LIKE '%ROLE_DEV%' OR u.roles LIKE '%ROLE_ADMIN%' OR u.roles LIKE '%ROLE_EDITOR%'")
                ->addSelect("CASE
                    WHEN u.roles LIKE '%ROLE_DEV%' THEN 0
                    WHEN u.roles LIKE '%ROLE_ADMIN%' THEN 1
                    WHEN u.roles LIKE '%ROLE_EDITOR%' THEN 2
                    ELSE 3 END AS HIDDEN role_order")
                ->orderBy('role_order', SortDirection::Ascending)
                ->addOrderBy('u.nom', SortDirection::Ascending)
                ->getQuery()
                ->getResult(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        AccountActivationMailer $activationMailer,
        PermissionChecker $permissions,
        ProfilDefaults $defaults,
    ): Response {
        $defaults->seedIfEmpty();
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('error', sprintf('Un compte existe déjà avec l\'adresse "%s".', $user->getEmail()));
            } else {
                $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(16))));
                $this->applyPermissions($request, $user, $permissions);
                $em->persist($user);
                $em->flush();

                $activationMailer->sendActivationEmail($user);

                $this->addFlash('success', sprintf('Utilisateur créé. Un email a été envoyé à %s pour définir le mot de passe.', $user->getEmail()));

                return $this->redirectToRoute('admin_user_index');
            }
        }

        return $this->render('admin/user/form.html.twig', [
            'form'     => $form,
            'user'     => $user,
            'selected' => $form->isSubmitted() ? $request->request->all('permissions') : [],
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $em, UserRepository $userRepository, PermissionChecker $permissions, ProfilDefaults $defaults): Response
    {
        $defaults->seedIfEmpty();
        if (!$this->isGranted(UserVoter::EDIT, $user)) {
            throw $this->createAccessDeniedException('Seul un développeur peut modifier un compte développeur.');
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $conflict = $userRepository->createQueryBuilder('u')
                ->andWhere('u.email = :email')
                ->andWhere('u.id != :id')
                ->setParameter('email', $user->getEmail())
                ->setParameter('id', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($conflict) {
                $this->addFlash('error', 'Un autre compte utilise déjà cette adresse email.');
            } else {
                $this->applyPermissions($request, $user, $permissions);
                $em->flush();
                $this->addFlash('success', 'Utilisateur mis à jour.');

                return $this->redirectToRoute('admin_user_index');
            }
        }

        return $this->render('admin/user/form.html.twig', [
            'form'     => $form,
            'user'     => $user,
            'selected' => $form->isSubmitted() ? $request->request->all('permissions') : $permissions->grantedCodes($user),
        ]);
    }

    /**
     * Enregistre les autorisations cochées dans la matrice : ce qui dépasse le profil est « ajouté »,
     * ce qui manque est « retiré ». Sans profil et avec tout coché, l'accès reste complet (y compris
     * pour les futures autorisations). On n'accorde jamais plus que ce que l'on possède soi-même.
     */
    private function applyPermissions(Request $request, User $user, PermissionChecker $permissions): void
    {
        if (in_array('ROLE_DEV', $user->getRoles(), true)) {
            $user->setProfil(null)->setPermissionsAjoutees([])->setPermissionsRetirees([])->setAccesRestreint(false);

            return;
        }

        $submitted = PermissionCatalog::sanitize($request->request->all('permissions'));

        // Les autorisations que l'auteur de la modification n'a pas lui-même restent celles qu'avait l'utilisateur.
        $actor = $this->getUser();
        if ($actor instanceof User && !$permissions->hasFullAccess($actor)) {
            $mine      = $permissions->grantedCodes($actor);
            $before    = $user->getId() ? $permissions->grantedCodes($user) : [];
            $submitted = array_values(array_unique(array_merge(array_intersect($submitted, $mine), array_diff($before, $mine))));
        }

        $base = $user->getProfil()?->getPermissions() ?? [];
        if (null === $user->getProfil() && [] === array_diff(PermissionCatalog::all(), $submitted)) {
            $user->setPermissionsAjoutees([])->setPermissionsRetirees([])->setAccesRestreint(false);

            return;
        }

        $user->setPermissionsAjoutees(array_diff($submitted, $base))
            ->setPermissionsRetirees(array_diff($base, $submitted))
            ->setAccesRestreint(true);
    }

    #[Route('/{id}/renvoyer-activation', name: 'admin_user_resend_activation', methods: ['POST'])]
    public function resendActivation(Request $request, User $user, AccountActivationMailer $activationMailer): Response
    {
        if ($this->isCsrfTokenValid('resend-activation-'.$user->getId(), $request->request->get('_token'))) {
            $activationMailer->sendActivationEmail($user);
            $this->addFlash('success', sprintf('Email d\'activation renvoyé à %s.', $user->getEmail()));
        }

        return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted(UserVoter::DELETE, $user)) {
            throw $this->createAccessDeniedException('Seul un développeur peut supprimer un compte développeur.');
        }

        if ($this->isCsrfTokenValid('delete-user-'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('admin_user_index');
    }
}

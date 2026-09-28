<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AppearanceType;
use App\Form\NotificationPreferencesType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Paramètres personnels du compte connecté (profil, sécurité, apparence,
 * préférences de notification) — distinct de "Réglages" qui configure le
 * site du club, accessible à tous les utilisateurs authentifiés.
 */
#[Route('/admin/parametres')]
#[IsGranted('ROLE_USER')]
class AccountSettingsController extends AbstractController
{
    #[Route('/profil', name: 'admin_parametres_profil', methods: ['GET', 'POST'])]
    public function profil(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user);
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
                $em->flush();
                $this->addFlash('success', 'Profil mis à jour.');

                return $this->redirectToRoute('admin_parametres_profil');
            }
        }

        $passwordForm = $this->container->get('form.factory')->createNamedBuilder('password_change', FormType::class, null, ['csrf_token_id' => 'change_password'])
            ->add('currentPassword', PasswordType::class, [
                'label'       => 'Mot de passe actuel',
                'constraints' => [new NotBlank(message: 'Veuillez saisir votre mot de passe actuel.')],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'first_options'   => ['label' => 'Nouveau mot de passe'],
                'second_options'  => ['label' => 'Confirmer le nouveau mot de passe'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints'     => [new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')],
            ])
            ->add('save', SubmitType::class, ['label' => 'Mettre à jour le mot de passe'])
            ->getForm();
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            if (!$hasher->isPasswordValid($user, $passwordForm->get('currentPassword')->getData())) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            } else {
                $user->setPassword($hasher->hashPassword($user, $passwordForm->get('newPassword')->getData()));
                $em->flush();
                $this->addFlash('success', 'Votre mot de passe a bien été mis à jour.');

                return $this->redirectToRoute('admin_parametres_profil');
            }
        }

        return $this->render('admin/parametres/profil.html.twig', [
            'form'          => $form,
            'passwordForm'  => $passwordForm,
            'active_tab'    => 'profil',
        ]);
    }

    #[Route('/apparence', name: 'admin_parametres_apparence', methods: ['GET', 'POST'])]
    public function apparence(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(AppearanceType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Préférences d\'apparence mises à jour.');

            return $this->redirectToRoute('admin_parametres_apparence');
        }

        return $this->render('admin/parametres/apparence.html.twig', [
            'form'       => $form,
            'active_tab' => 'apparence',
        ]);
    }

    #[Route('/preferences', name: 'admin_parametres_preferences', methods: ['GET', 'POST'])]
    public function preferences(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(NotificationPreferencesType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Préférences de notification mises à jour.');

            return $this->redirectToRoute('admin_parametres_preferences');
        }

        return $this->render('admin/parametres/preferences.html.twig', [
            'form'       => $form,
            'active_tab' => 'preferences',
        ]);
    }
}

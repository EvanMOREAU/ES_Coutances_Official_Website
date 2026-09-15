<?php

namespace App\Controller\Admin;

use App\Entity\ContactSettings;
use App\Entity\HomepageBanner;
use App\Entity\MatchLive;
use App\Entity\User;
use App\Repository\ContactSettingsRepository;
use App\Repository\HomepageBannerRepository;
use App\Repository\MatchLiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * Section "Réglages" unifiée : regroupe les réglages qui étaient auparavant
 * chacun sur leur propre page (bannière d'accueil, page de contact, match en
 * live, mot de passe) derrière une seule entrée de menu et une navigation
 * par onglets, au lieu de 4 pages isolées et déconnectées.
 */
#[IsGranted('ROLE_USER')]
class ReglagesController extends AbstractController
{
    #[AdminRoute(path: '/reglages/accueil', name: 'reglages_accueil', options: ['methods' => ['GET', 'POST']])]
    #[IsGranted('ROLE_ADMIN')]
    public function accueil(Request $request, HomepageBannerRepository $repo, EntityManagerInterface $em): Response
    {
        $entity = $repo->getSingleton();
        if (!$entity) {
            $entity = new HomepageBanner();
            $em->persist($entity);
        }

        $form = $this->createFormBuilder($entity)
            ->add('titre', TextType::class, [
                'label' => 'Titre (optionnel)',
                'required' => false,
                'help' => "Affiché en surimpression sur l'image, laisser vide pour n'afficher que l'image.",
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Image',
                'required' => false,
                'allow_delete' => false,
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien de la bannière',
                'required' => false,
                'help' => 'Ex: lien de streaming du prochain match, page événement, etc.',
            ])
            ->add('actif', CheckboxType::class, [
                'label' => "Afficher la bannière sur la page d'accueil",
                'required' => false,
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', "Bannière d'accueil mise à jour.");

            return $this->redirectToRoute('admin_reglages_accueil');
        }

        return $this->render('admin/reglages/accueil.html.twig', [
            'form' => $form,
            'active_tab' => 'accueil',
        ]);
    }

    #[AdminRoute(path: '/reglages/contact', name: 'reglages_contact', options: ['methods' => ['GET', 'POST']])]
    #[IsGranted('ROLE_ADMIN')]
    public function contact(Request $request, ContactSettingsRepository $repo, EntityManagerInterface $em): Response
    {
        $entity = $repo->getSingleton();
        if (!$entity) {
            $entity = new ContactSettings();
            $em->persist($entity);
        }

        $form = $this->createFormBuilder($entity)
            ->add('email', EmailType::class, [
                'label' => 'Email de contact',
                'help' => 'Adresse qui recevra les messages envoyés depuis le formulaire de contact du site.',
                'constraints' => [new NotBlank(message: 'Indiquez une adresse email.')],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'help' => 'Ex: Stade Paul-Maundrell, BP 602, 50200 Coutances',
                'constraints' => [new NotBlank(message: 'Indiquez une adresse.')],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'help' => 'Ex: 02 33 47 04 90',
                'constraints' => [new NotBlank(message: 'Indiquez un numéro de téléphone.')],
            ])
            ->add('horaireLundi', TextType::class, [
                'label' => 'Horaires - Lundi',
                'help' => 'Ex: 14h00 – 18h00, ou "Fermé"',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireMardi', TextType::class, [
                'label' => 'Horaires - Mardi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireMercredi', TextType::class, [
                'label' => 'Horaires - Mercredi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireJeudi', TextType::class, [
                'label' => 'Horaires - Jeudi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireVendredi', TextType::class, [
                'label' => 'Horaires - Vendredi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireSamedi', TextType::class, [
                'label' => 'Horaires - Samedi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireDimanche', TextType::class, [
                'label' => 'Horaires - Dimanche',
                'constraints' => [new NotBlank()],
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Page de contact mise à jour.');

            return $this->redirectToRoute('admin_reglages_contact');
        }

        return $this->render('admin/reglages/contact.html.twig', [
            'form' => $form,
            'active_tab' => 'contact',
        ]);
    }

    #[AdminRoute(path: '/reglages/match-live', name: 'reglages_match_live', options: ['methods' => ['GET', 'POST']])]
    #[IsGranted('ROLE_ADMIN')]
    public function matchLive(Request $request, MatchLiveRepository $repo, EntityManagerInterface $em): Response
    {
        $entity = $repo->getSingleton();
        if (!$entity) {
            $entity = new MatchLive();
            $em->persist($entity);
        }

        $form = $this->createFormBuilder($entity)
            ->add('enLigne', CheckboxType::class, [
                'label' => 'Un match est actuellement en direct',
                'required' => false,
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien vers le direct',
                'required' => false,
                'help' => 'Ex: lien de streaming, page Facebook/YouTube live, etc. Utilisé par le bouton "Match en Live" du site.',
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Réglages du match en live mis à jour.');

            return $this->redirectToRoute('admin_reglages_match_live');
        }

        return $this->render('admin/reglages/match_live.html.twig', [
            'form' => $form,
            'active_tab' => 'match_live',
        ]);
    }

    #[AdminRoute(path: '/reglages/mon-compte', name: 'reglages_compte', options: ['methods' => ['GET', 'POST']])]
    public function compte(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'constraints' => [new NotBlank(message: 'Veuillez saisir votre mot de passe actuel.')],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmer le nouveau mot de passe'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => [new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')],
            ])
            ->add('save', SubmitType::class, ['label' => 'Mettre à jour le mot de passe'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');

                return $this->redirectToRoute('admin_reglages_compte');
            }

            $user->setPassword($hasher->hashPassword($user, $form->get('newPassword')->getData()));
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a bien été mis à jour.');

            return $this->redirectToRoute('admin_reglages_compte');
        }

        return $this->render('admin/reglages/compte.html.twig', [
            'form' => $form,
            'active_tab' => 'compte',
        ]);
    }
}

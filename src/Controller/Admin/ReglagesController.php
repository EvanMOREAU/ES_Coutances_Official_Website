<?php

namespace App\Controller\Admin;

use App\Entity\ContactSettings;
use App\Entity\HomepageBanner;
use App\Entity\MatchLive;
use App\Repository\ContactSettingsRepository;
use App\Repository\HomepageBannerRepository;
use App\Repository\MatchLiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * Section "Réglages" unifiée : regroupe les réglages qui étaient auparavant
 * chacun sur leur propre page (bannière d'accueil, page de contact, match en
 * live, mot de passe) derrière une seule entrée de menu et une navigation
 * par onglets, au lieu de 4 pages isolées et déconnectées.
 */
#[Route('/admin/reglages')]
#[IsGranted('ROLE_USER')]
class ReglagesController extends AbstractController
{
    #[Route('/accueil', name: 'admin_reglages_accueil', methods: ['GET', 'POST'])]
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
            'entity' => $entity,
            'active_tab' => 'accueil',
        ]);
    }

    #[Route('/contact', name: 'admin_reglages_contact', methods: ['GET', 'POST'])]
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
                'attr' => ['data-controller' => 'horaire'],
                'label' => 'Horaires - Lundi',
                'help' => 'Choisissez « Ouvert » et les plages d’ouverture, ou laissez « Fermé ».',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireMardi', TextType::class, [
                'attr' => ['data-controller' => 'horaire'],
                'label' => 'Horaires - Mardi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireMercredi', TextType::class, [
                'attr' => ['data-controller' => 'horaire'],
                'label' => 'Horaires - Mercredi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireJeudi', TextType::class, [
                'attr' => ['data-controller' => 'horaire'],
                'label' => 'Horaires - Jeudi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireVendredi', TextType::class, [
                'attr' => ['data-controller' => 'horaire'],
                'label' => 'Horaires - Vendredi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireSamedi', TextType::class, [
                'attr' => ['data-controller' => 'horaire'],
                'label' => 'Horaires - Samedi',
                'constraints' => [new NotBlank()],
            ])
            ->add('horaireDimanche', TextType::class, [
                'attr' => ['data-controller' => 'horaire'],
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

    #[Route('/match-live', name: 'admin_reglages_match_live', methods: ['GET', 'POST'])]
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

}

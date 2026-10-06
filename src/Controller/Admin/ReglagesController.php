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
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormError;
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
    public function accueil(Request $request, HomepageBannerRepository $repo, MatchLiveRepository $matchRepo, EntityManagerInterface $em): Response
    {
        $match = $matchRepo->getSingleton();
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
            ->add('matchDebut', DateTimeType::class, [
                'mapped' => false,
                'label' => 'Match en direct — début',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $match?->isProgramme() ? $match->getDebutAt() : null,
                'help' => 'Optionnel : le site affichera automatiquement le match « en direct » entre le début et la fin, avec le lien de la bannière.',
            ])
            ->add('matchFin', DateTimeType::class, [
                'mapped' => false,
                'label' => 'Match en direct — fin',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $match?->isProgramme() ? $match->getFinAt() : null,
            ])
            ->add('matchEnDirect', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Un match est actuellement en direct (immédiatement)',
                'required' => false,
                'help' => "Si coché, le lien de la bannière devient le lien du direct et le bouton « Match en Live » du site s'active. Laissé décoché, le match en live n'est pas modifié (actuellement : ".($match?->isEnDirect() ? 'en direct' : 'pas de direct').').',
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $debut = $form->get('matchDebut')->getData();
            $fin   = $form->get('matchFin')->getData();
            if (($debut === null) !== ($fin === null)) {
                $form->get($debut === null ? 'matchDebut' : 'matchFin')->addError(new FormError('Indiquez le début et la fin du direct.'));
            } elseif ($debut !== null && $fin <= $debut) {
                $form->get('matchFin')->addError(new FormError('La fin doit être après le début.'));
            }
            if (($form->get('matchEnDirect')->getData() || $debut !== null) && !$entity->getUrl()) {
                $form->get('url')->addError(new FormError('Indiquez le lien du direct (lien de la bannière) pour activer le match en live.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $debut = $form->get('matchDebut')->getData();
            $fin   = $form->get('matchFin')->getData();
            if ($form->get('matchEnDirect')->getData() || $debut !== null) {
                if (!$match) {
                    $match = new MatchLive();
                    $em->persist($match);
                }
                $match->setUrl($entity->getUrl())->setUpdatedAt(new \DateTimeImmutable());
                if ($form->get('matchEnDirect')->getData()) {
                    $match->setEnLigne(true);
                }
                if ($debut !== null) {
                    $match->setDebutAt($debut)->setFinAt($fin);
                }
            }
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
            ->add('debutAt', DateTimeType::class, [
                'label' => 'Début du direct',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Entre le début et la fin, le site affiche automatiquement le match « en direct ». Laissez vide si vous préférez l\'activer à la main.',
            ])
            ->add('finAt', DateTimeType::class, [
                'label' => 'Fin du direct',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
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
            if (($entity->getDebutAt() === null) !== ($entity->getFinAt() === null)) {
                $form->get($entity->getDebutAt() === null ? 'debutAt' : 'finAt')->addError(new FormError('Indiquez le début et la fin du direct.'));
            } elseif ($entity->getDebutAt() !== null && $entity->getFinAt() <= $entity->getDebutAt()) {
                $form->get('finAt')->addError(new FormError('La fin doit être après le début.'));
            }
        }

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

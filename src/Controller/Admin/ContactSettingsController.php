<?php

namespace App\Controller\Admin;

use App\Entity\ContactSettings;
use App\Repository\ContactSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Page de réglage unique pour les informations affichées sur la page
 * "Contact" du site public (pas de liste ni d'identifiant dans l'URL,
 * contrairement à un CRUD classique).
 */
class ContactSettingsController extends AbstractController
{
    #[AdminRoute(path: '/page-contact', name: 'contact_settings', options: ['methods' => ['GET', 'POST']])]
    public function edit(Request $request, ContactSettingsRepository $repo, EntityManagerInterface $em): Response
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

            return $this->redirectToRoute('admin_contact_settings');
        }

        return $this->render('admin/contact_settings.html.twig', [
            'form' => $form,
        ]);
    }
}

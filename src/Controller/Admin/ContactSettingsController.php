<?php

namespace App\Controller\Admin;

use App\Entity\ContactSettings;
use App\Repository\ContactSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Page de réglage unique pour l'adresse recevant les messages du formulaire
 * de contact du site public (pas de liste ni d'identifiant dans l'URL,
 * contrairement à un CRUD classique).
 */
class ContactSettingsController extends AbstractController
{
    #[AdminRoute(path: '/email-contact', name: 'contact_settings', options: ['methods' => ['GET', 'POST']])]
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
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Email de contact mis à jour.');

            return $this->redirectToRoute('admin_contact_settings');
        }

        return $this->render('admin/contact_settings.html.twig', [
            'form' => $form,
        ]);
    }
}

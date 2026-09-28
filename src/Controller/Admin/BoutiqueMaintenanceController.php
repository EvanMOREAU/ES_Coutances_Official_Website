<?php

namespace App\Controller\Admin;

use App\Entity\BoutiqueSettings;
use App\Repository\BoutiqueSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Mise en maintenance de la boutique publique. */
#[Route('/admin/boutique/maintenance')]
class BoutiqueMaintenanceController extends AbstractController
{
    #[Route('', name: 'admin_boutique_maintenance', methods: ['GET', 'POST'])]
    public function index(Request $request, BoutiqueSettingsRepository $repo, EntityManagerInterface $em): Response
    {
        $entity = $repo->getSingleton();
        if (!$entity) {
            $entity = new BoutiqueSettings();
            $em->persist($entity);
        }

        $form = $this->createFormBuilder($entity)
            ->add('enMaintenance', CheckboxType::class, [
                'label'    => 'Boutique en maintenance (masquée du public)',
                'required' => false,
                'help'     => 'Les visiteurs voient une page "boutique indisponible" à la place du catalogue. Les comptes développeur continuent de voir la boutique normalement, avec un bandeau d\'avertissement.',
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Réglages de la boutique mis à jour.');

            return $this->redirectToRoute('admin_boutique_maintenance');
        }

        return $this->render('admin/boutique/maintenance.html.twig', ['form' => $form]);
    }
}

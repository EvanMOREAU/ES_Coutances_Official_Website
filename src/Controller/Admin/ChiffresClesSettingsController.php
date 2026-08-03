<?php

namespace App\Controller\Admin;

use App\Entity\ChiffresCles;
use App\Repository\ChiffresClesRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page de réglage unique pour les chiffres clés de la page d'accueil
 * (pas de liste ni d'identifiant dans l'URL, contrairement à un CRUD classique).
 */
class ChiffresClesSettingsController extends AbstractController
{
    #[AdminRoute(path: '/chiffres-cles', name: 'chiffres_cles', options: ['methods' => ['GET', 'POST']])]
    public function edit(Request $request, ChiffresClesRepository $repo, EntityManagerInterface $em): Response
    {
        $entity = $repo->getSingleton();
        if (!$entity) {
            $entity = new ChiffresCles();
            $em->persist($entity);
        }

        $form = $this->createFormBuilder($entity)
            ->add('nbLicencies', IntegerType::class, ['label' => 'Licenciés'])
            ->add('nbEducateurs', IntegerType::class, ['label' => 'Éducateurs diplômés'])
            ->add('nbBenevoles', IntegerType::class, ['label' => 'Bénévoles actifs'])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Chiffres clés mis à jour.');

            return $this->redirectToRoute('admin_chiffres_cles');
        }

        return $this->render('admin/chiffres_cles.html.twig', [
            'form' => $form,
        ]);
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\MatchLive;
use App\Repository\MatchLiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page de réglage unique pour le bandeau "Match en Live" du site public
 * (pas de liste ni d'identifiant dans l'URL, contrairement à un CRUD classique).
 */
class MatchLiveSettingsController extends AbstractController
{
    #[AdminRoute(path: '/match-live', name: 'match_live', options: ['methods' => ['GET', 'POST']])]
    public function edit(Request $request, MatchLiveRepository $repo, EntityManagerInterface $em): Response
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

            return $this->redirectToRoute('admin_match_live');
        }

        return $this->render('admin/match_live.html.twig', [
            'form' => $form,
        ]);
    }
}

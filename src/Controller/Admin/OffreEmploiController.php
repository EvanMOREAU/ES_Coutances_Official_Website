<?php

namespace App\Controller\Admin;

use App\Entity\OffreEmploi;
use App\Form\OffreEmploiType;
use App\Repository\OffreEmploiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/offres-emploi')]
class OffreEmploiController extends AbstractController
{
    #[Route('', name: 'admin_offre_emploi_index', methods: ['GET'])]
    public function index(OffreEmploiRepository $repository): Response
    {
        return $this->render('admin/offre_emploi/index.html.twig', [
            'offres' => $repository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_offre_emploi_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $offre = new OffreEmploi();
        $form  = $this->createForm(OffreEmploiType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offre);
            $em->flush();

            $this->addFlash('success', "Offre d'emploi créée.");

            return $this->redirectToRoute('admin_offre_emploi_index');
        }

        return $this->render('admin/offre_emploi/form.html.twig', [
            'form'  => $form,
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_offre_emploi_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OffreEmploi $offre, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OffreEmploiType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $offre->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', "Offre d'emploi mise à jour.");

            return $this->redirectToRoute('admin_offre_emploi_index');
        }

        return $this->render('admin/offre_emploi/form.html.twig', [
            'form'  => $form,
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_offre_emploi_delete', methods: ['POST'])]
    public function delete(Request $request, OffreEmploi $offre, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-offre-'.$offre->getId(), $request->request->get('_token'))) {
            $em->remove($offre);
            $em->flush();
            $this->addFlash('success', "Offre d'emploi supprimée.");
        }

        return $this->redirectToRoute('admin_offre_emploi_index');
    }
}

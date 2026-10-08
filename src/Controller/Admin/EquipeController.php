<?php

namespace App\Controller\Admin;

use App\Entity\Equipe;
use App\Form\EquipeType;
use App\Repository\EquipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/equipes')]
class EquipeController extends AbstractController
{
    #[Route('', name: 'admin_equipe_index', methods: ['GET'])]
    public function index(EquipeRepository $repository): Response
    {
        return $this->render('admin/equipe/index.html.twig', [
            'equipes' => $repository->findBy([], ['categorie' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_equipe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $equipe = new Equipe();
        $form   = $this->createForm(EquipeType::class, $equipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($equipe);
            $em->flush();
            $this->addFlash('success', 'Équipe créée.');

            return $this->redirectToRoute('admin_equipe_index');
        }

        return $this->render('admin/equipe/form.html.twig', ['form' => $form, 'equipe' => $equipe]);
    }

    #[Route('/{id}/modifier', name: 'admin_equipe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipe $equipe, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EquipeType::class, $equipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Équipe mise à jour.');

            return $this->redirectToRoute('admin_equipe_index');
        }

        return $this->render('admin/equipe/form.html.twig', ['form' => $form, 'equipe' => $equipe]);
    }

    #[Route('/{id}/supprimer', name: 'admin_equipe_delete', methods: ['POST'])]
    public function delete(Request $request, Equipe $equipe, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-equipe-'.$equipe->getId(), $request->request->get('_token'))) {
            $em->remove($equipe);
            $em->flush();
            $this->addFlash('success', 'Équipe supprimée.');
        }

        return $this->redirectToRoute('admin_equipe_index');
    }
}

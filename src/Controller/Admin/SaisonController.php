<?php

namespace App\Controller\Admin;

use App\Entity\Licencie;
use App\Entity\Saison;
use App\Form\SaisonType;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/saisons')]
class SaisonController extends AbstractController
{
    #[Route('', name: 'admin_saison_index', methods: ['GET'])]
    public function index(SaisonRepository $repository): Response
    {
        return $this->render('admin/saison/index.html.twig', [
            'saisons' => $repository->findBy([], ['dateDebut' => 'DESC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_saison_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $saison = new Saison();
        $form   = $this->createForm(SaisonType::class, $saison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($saison);
            $em->flush();

            $this->addFlash('success', 'Saison créée.');

            return $this->redirectToRoute('admin_saison_index');
        }

        return $this->render('admin/saison/form.html.twig', [
            'form'  => $form,
            'saison' => $saison,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_saison_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Saison $saison, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SaisonType::class, $saison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Saison mise à jour.');

            return $this->redirectToRoute('admin_saison_index');
        }

        return $this->render('admin/saison/form.html.twig', [
            'form'  => $form,
            'saison' => $saison,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_saison_delete', methods: ['POST'])]
    public function delete(Request $request, Saison $saison, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-saison-'.$saison->getId(), $request->request->get('_token'))) {
            if ($em->getRepository(Licencie::class)->count(['saison' => $saison]) > 0) {
                $this->addFlash('error', 'Cette saison est utilisée par des licenciés : elle ne peut pas être supprimée (archivez-la à la place).');

                return $this->redirectToRoute('admin_saison_index');
            }

            $em->remove($saison);
            $em->flush();
            $this->addFlash('success', 'Saison supprimée.');
        }

        return $this->redirectToRoute('admin_saison_index');
    }
}

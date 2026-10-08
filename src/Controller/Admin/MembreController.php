<?php

namespace App\Controller\Admin;

use App\Entity\Membre;
use App\Form\MembreType;
use App\Repository\MembreRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/encadrement')]
class MembreController extends AbstractController
{
    #[Route('', name: 'admin_membre_index', methods: ['GET'])]
    public function index(MembreRepository $repository): Response
    {
        return $this->render('admin/membre/index.html.twig', [
            'membres' => $repository->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_membre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $membre = new Membre();
        $membre->setOrdre($ordreService->getNextOrdre(Membre::class));

        $form = $this->createForm(MembreType::class, $membre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ordreService->ensureUniqueOrdre(Membre::class, $membre);
            $em->persist($membre);
            $em->flush();

            $this->addFlash('success', 'Membre créé.');

            return $this->redirectToRoute('admin_membre_index');
        }

        return $this->render('admin/membre/form.html.twig', [
            'form'   => $form,
            'membre' => $membre,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_membre_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Membre $membre, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $form = $this->createForm(MembreType::class, $membre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ordreService->ensureUniqueOrdre(Membre::class, $membre);
            $em->flush();

            $this->addFlash('success', 'Membre mis à jour.');

            return $this->redirectToRoute('admin_membre_index');
        }

        return $this->render('admin/membre/form.html.twig', [
            'form'   => $form,
            'membre' => $membre,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_membre_delete', methods: ['POST'])]
    public function delete(Request $request, Membre $membre, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-membre-'.$membre->getId(), $request->request->get('_token'))) {
            $em->remove($membre);
            $em->flush();
            $this->addFlash('success', 'Membre supprimé.');
        }

        return $this->redirectToRoute('admin_membre_index');
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\PageContenu;
use App\Form\PageContenuType;
use App\Repository\PageContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pages')]
class PageContenuController extends AbstractController
{
    #[Route('', name: 'admin_page_contenu_index', methods: ['GET'])]
    public function index(PageContenuRepository $repository): Response
    {
        return $this->render('admin/page_contenu/index.html.twig', [
            'pages' => $repository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_page_contenu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $page = new PageContenu();
        $form = $this->createForm(PageContenuType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $page->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($page);
            $em->flush();

            $this->addFlash('success', 'Page créée.');

            return $this->redirectToRoute('admin_page_contenu_index');
        }

        return $this->render('admin/page_contenu/form.html.twig', [
            'form' => $form,
            'page' => $page,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_page_contenu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PageContenu $page, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PageContenuType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $page->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Page mise à jour.');

            return $this->redirectToRoute('admin_page_contenu_index');
        }

        return $this->render('admin/page_contenu/form.html.twig', [
            'form' => $form,
            'page' => $page,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_page_contenu_delete', methods: ['POST'])]
    public function delete(Request $request, PageContenu $page, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-page-'.$page->getId(), $request->request->get('_token'))) {
            $em->remove($page);
            $em->flush();
            $this->addFlash('success', 'Page supprimée.');
        }

        return $this->redirectToRoute('admin_page_contenu_index');
    }
}

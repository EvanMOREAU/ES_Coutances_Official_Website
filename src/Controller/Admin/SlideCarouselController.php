<?php

namespace App\Controller\Admin;

use App\Entity\SlideCarousel;
use App\Form\SlideCarouselType;
use App\Repository\SlideCarouselRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/carousel')]
class SlideCarouselController extends AbstractController
{
    #[Route('', name: 'admin_slide_carousel_index', methods: ['GET'])]
    public function index(SlideCarouselRepository $repository): Response
    {
        return $this->render('admin/slide_carousel/index.html.twig', [
            'slides' => $repository->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_slide_carousel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $slide = new SlideCarousel();
        $slide->setOrdre($ordreService->getNextOrdre(SlideCarousel::class));

        $form = $this->createForm(SlideCarouselType::class, $slide, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($slide);
            $em->flush();

            $this->addFlash('success', 'Slide créée.');

            return $this->redirectToRoute('admin_slide_carousel_index');
        }

        return $this->render('admin/slide_carousel/form.html.twig', [
            'form'  => $form,
            'slide' => $slide,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_slide_carousel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SlideCarousel $slide, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SlideCarouselType::class, $slide);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Slide mise à jour.');

            return $this->redirectToRoute('admin_slide_carousel_index');
        }

        return $this->render('admin/slide_carousel/form.html.twig', [
            'form'  => $form,
            'slide' => $slide,
        ]);
    }

    #[Route('/reordonner', name: 'admin_slide_carousel_reorder', methods: ['POST'])]
    public function reorder(Request $request, SlideCarouselRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('reorder-slide_carousel', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Jeton invalide.'], 403);
        }

        $slides = $repository->findBy(['id' => $data['ids'] ?? []]);
        $byId   = [];
        foreach ($slides as $slide) {
            $byId[$slide->getId()] = $slide;
        }

        foreach (array_values($data['ids'] ?? []) as $index => $id) {
            $byId[$id]?->setOrdre($index);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/supprimer', name: 'admin_slide_carousel_delete', methods: ['POST'])]
    public function delete(Request $request, SlideCarousel $slide, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-slide-'.$slide->getId(), $request->request->get('_token'))) {
            $em->remove($slide);
            $em->flush();
            $this->addFlash('success', 'Slide supprimée.');
        }

        return $this->redirectToRoute('admin_slide_carousel_index');
    }
}

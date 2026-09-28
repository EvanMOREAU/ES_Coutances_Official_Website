<?php

namespace App\Controller\Admin;

use App\Entity\PageContenu;
use App\Entity\RejoindreCard;
use App\Form\RejoindreCardType;
use App\Repository\PageContenuRepository;
use App\Repository\RejoindreCardRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/nous-rejoindre')]
class RejoindreCardController extends AbstractController
{
    #[Route('', name: 'admin_rejoindre_card_index', methods: ['GET'])]
    public function index(RejoindreCardRepository $repository): Response
    {
        return $this->render('admin/rejoindre_card/index.html.twig', [
            'cards' => $repository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_rejoindre_card_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService, PageContenuRepository $pageRepo): Response
    {
        $card = new RejoindreCard();
        $card->setOrdre($ordreService->getNextOrdre(RejoindreCard::class));

        $form = $this->createForm(RejoindreCardType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleNouvellePage($em, $card, $pageRepo);
            $ordreService->ensureUniqueOrdre(RejoindreCard::class, $card);
            $em->persist($card);
            $em->flush();

            $this->addFlash('success', 'Carte créée.');

            return $this->redirectToRoute('admin_rejoindre_card_index');
        }

        return $this->render('admin/rejoindre_card/form.html.twig', [
            'form' => $form,
            'card' => $card,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_rejoindre_card_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RejoindreCard $card, EntityManagerInterface $em, OrdreService $ordreService, PageContenuRepository $pageRepo): Response
    {
        $form = $this->createForm(RejoindreCardType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleNouvellePage($em, $card, $pageRepo);
            $ordreService->ensureUniqueOrdre(RejoindreCard::class, $card);
            $em->flush();

            $this->addFlash('success', 'Carte mise à jour.');

            return $this->redirectToRoute('admin_rejoindre_card_index');
        }

        return $this->render('admin/rejoindre_card/form.html.twig', [
            'form' => $form,
            'card' => $card,
        ]);
    }

    /** Active ou désactive la carte d'un clic, sans passer par le formulaire de modification. */
    #[Route('/{id}/basculer', name: 'admin_rejoindre_card_toggle', methods: ['POST'])]
    public function toggle(Request $request, RejoindreCard $card, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('toggle-card-'.$card->getId(), $request->request->get('_token'))) {
            $card->setActif(!$card->isActif());
            $em->flush();
            $this->addFlash('success', $card->isActif() ? 'Carte activée : elle est de nouveau visible sur le site.' : 'Carte désactivée : elle n’est plus visible sur le site.');
        }

        return $this->redirectToRoute('admin_rejoindre_card_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_rejoindre_card_delete', methods: ['POST'])]
    public function delete(Request $request, RejoindreCard $card, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-card-'.$card->getId(), $request->request->get('_token'))) {
            $em->remove($card);
            $em->flush();
            $this->addFlash('success', 'Carte supprimée.');
        }

        return $this->redirectToRoute('admin_rejoindre_card_index');
    }

    private function handleNouvellePage(EntityManagerInterface $em, RejoindreCard $card, PageContenuRepository $pageRepo): void
    {
        $titre = trim((string) $card->getNouvellePageTitre());

        if ('' === $titre || null !== $card->getPageDetail()) {
            return;
        }

        $slug     = (new AsciiSlugger())->slug($titre)->lower()->toString();
        $baseSlug = $slug;
        $i        = 2;
        while ($pageRepo->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug.'-'.$i++;
        }

        $page = new PageContenu();
        $page->setTitre($titre);
        $page->setSlug($slug);
        $page->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($page);
        $card->setPageDetail($page);

        $this->addFlash('success', sprintf('La page "%s" a été créée et liée à cette carte.', $titre));
    }
}

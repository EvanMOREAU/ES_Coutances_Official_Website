<?php

namespace App\Controller\Admin;

use App\Entity\ContratPartenaire;
use App\Entity\ContratPartenaireDocument;
use App\Entity\Partenaire;
use App\Form\PartenaireType;
use App\Form\PartenaireWizardType;
use App\Repository\PartenaireRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/partenaires')]
class PartenaireController extends AbstractController
{
    #[Route('', name: 'admin_partenaire_index', methods: ['GET'])]
    public function index(PartenaireRepository $repository): Response
    {
        return $this->render('admin/partenaire/index.html.twig', [
            'partenaires' => $repository->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/{id}', name: 'admin_partenaire_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Partenaire $partenaire): Response
    {
        return $this->render('admin/partenaire/show.html.twig', [
            'partenaire' => $partenaire,
        ]);
    }

    /** Création guidée : partenaire, premier contrat, règlements, tâches et notes en une fois. */
    #[Route('/nouveau', name: 'admin_partenaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $partenaire = new Partenaire();
        $partenaire->setOrdre($ordreService->getNextOrdre(Partenaire::class));

        $form = $this->createForm(PartenaireWizardType::class, $partenaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contrat = $form->get('contrat')->getData();
            $this->renumber($contrat);
            $partenaire->addContrat($contrat);

            $document = $form->get('document')->getData();
            if ($document instanceof ContratPartenaireDocument && $document->getFichierFile()) {
                $document->setNomOriginal($document->getFichierFile()->getClientOriginalName());
                $contrat->addDocument($document);
            }

            $em->persist($partenaire);
            $em->flush();

            $this->addFlash('success', 'Partenaire créé.');

            return $this->redirectToRoute('admin_partenaire_show', ['id' => $partenaire->getId()]);
        }

        return $this->render('admin/partenaire/wizard.html.twig', [
            'form'       => $form,
            'partenaire' => $partenaire,
        ]);
    }

    /** Les règlements et tâches sont numérotés dans l'ordre de saisie (voir ContratPartenaireController). */
    private function renumber(ContratPartenaire $contrat): void
    {
        $i = 1;
        foreach ($contrat->getReglements() as $reglement) {
            $reglement->setOrdre($i++);
        }
        $i = 0;
        foreach ($contrat->getTaches() as $tache) {
            $tache->setOrdre($i++);
        }
    }

    #[Route('/{id}/modifier', name: 'admin_partenaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Partenaire $partenaire, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PartenaireType::class, $partenaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Partenaire mis à jour.');

            return $this->redirectToRoute('admin_partenaire_index');
        }

        return $this->render('admin/partenaire/form.html.twig', [
            'form'       => $form,
            'partenaire' => $partenaire,
        ]);
    }

    #[Route('/reordonner', name: 'admin_partenaire_reorder', methods: ['POST'])]
    public function reorder(Request $request, PartenaireRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('reorder-partenaire', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Jeton invalide.'], 403);
        }

        $partenaires = $repository->findBy(['id' => $data['ids'] ?? []]);
        $byId        = [];
        foreach ($partenaires as $partenaire) {
            $byId[$partenaire->getId()] = $partenaire;
        }

        foreach (array_values($data['ids'] ?? []) as $index => $id) {
            $byId[$id]?->setOrdre($index);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/supprimer', name: 'admin_partenaire_delete', methods: ['POST'])]
    public function delete(Request $request, Partenaire $partenaire, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-partenaire-'.$partenaire->getId(), $request->request->get('_token'))) {
            $em->remove($partenaire);
            $em->flush();
            $this->addFlash('success', 'Partenaire supprimé.');
        }

        return $this->redirectToRoute('admin_partenaire_index');
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\ContratPartenaire;
use App\Entity\ContratPartenaireDocument;
use App\Form\ContratPartenaireDocumentType;
use App\Form\ContratPartenaireType;
use App\Repository\PartenaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrats d'un partenaire ou sponsor : montant, règlements échelonnés, tâches à réaliser
 * et documents hébergés (contrat signé…). Rattachés à un partenaire (templates/admin/partenaire/show.html.twig).
 */
#[Route('/admin/partenaires/contrats')]
class ContratPartenaireController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/nouveau', name: 'admin_partenaire_contrat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PartenaireRepository $partenaires): Response
    {
        $partenaire = $partenaires->find($request->query->getInt('partenaire'))
            ?? throw $this->createNotFoundException('Partenaire introuvable.');

        $contrat = new ContratPartenaire();
        $contrat->setPartenaire($partenaire);

        $form = $this->createForm(ContratPartenaireType::class, $contrat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->renumber($contrat);
            $this->em->persist($contrat);
            $this->em->flush();

            $this->addFlash('success', 'Contrat créé.');

            return $this->redirectToRoute('admin_partenaire_contrat_edit', ['id' => $contrat->getId()]);
        }

        return $this->render('admin/partenaire/contrat/form.html.twig', [
            'form'       => $form,
            'contrat'    => $contrat,
            'partenaire' => $partenaire,
        ]);
    }

    /** Fiche de lecture d'un contrat : aucune modification possible depuis cette page. */
    #[Route('/{id}', name: 'admin_partenaire_contrat_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(ContratPartenaire $contrat): Response
    {
        return $this->render('admin/partenaire/contrat/show.html.twig', [
            'contrat'    => $contrat,
            'partenaire' => $contrat->getPartenaire(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_partenaire_contrat_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ContratPartenaire $contrat): Response
    {
        $form = $this->createForm(ContratPartenaireType::class, $contrat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->renumber($contrat);
            $this->em->flush();

            $this->addFlash('success', 'Contrat mis à jour.');

            return $this->redirectToRoute('admin_partenaire_contrat_edit', ['id' => $contrat->getId()]);
        }

        $documentForm = $this->createForm(ContratPartenaireDocumentType::class, new ContratPartenaireDocument());

        return $this->render('admin/partenaire/contrat/form.html.twig', [
            'form'         => $form,
            'contrat'      => $contrat,
            'partenaire'   => $contrat->getPartenaire(),
            'documentForm' => $documentForm,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_partenaire_contrat_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, ContratPartenaire $contrat): Response
    {
        $partenaire = $contrat->getPartenaire();
        if ($this->isCsrfTokenValid('delete-contrat-partenaire-'.$contrat->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($contrat);
            $this->em->flush();
            $this->addFlash('success', 'Contrat supprimé.');
        }

        return $this->redirectToRoute('admin_partenaire_show', ['id' => $partenaire->getId()]);
    }

    #[Route('/{id}/documents', name: 'admin_partenaire_contrat_document_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addDocument(Request $request, ContratPartenaire $contrat): Response
    {
        $document = new ContratPartenaireDocument();
        $form     = $this->createForm(ContratPartenaireDocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploaded = $document->getFichierFile();
            if ($uploaded) {
                $document->setNomOriginal($uploaded->getClientOriginalName());
            }
            $contrat->addDocument($document);
            $this->em->flush();
            $this->addFlash('success', 'Document ajouté.');
        } else {
            $this->addFlash('error', 'Le document n\'a pas pu être ajouté : vérifiez le format et la taille du fichier.');
        }

        return $this->redirectToRoute('admin_partenaire_contrat_edit', ['id' => $contrat->getId()]);
    }

    #[Route('/documents/{id}/supprimer', name: 'admin_partenaire_contrat_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteDocument(Request $request, ContratPartenaireDocument $document): Response
    {
        $contrat = $document->getContrat();
        if ($this->isCsrfTokenValid('delete-contrat-partenaire-document-'.$document->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($document);
            $this->em->flush();
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('admin_partenaire_contrat_edit', ['id' => $contrat->getId()]);
    }

    /** Les règlements et tâches sont numérotés dans l'ordre de saisie. */
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
}

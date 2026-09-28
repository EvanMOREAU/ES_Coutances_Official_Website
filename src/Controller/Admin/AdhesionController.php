<?php

namespace App\Controller\Admin;

use App\Entity\Adhesion;
use App\Entity\AideFinanciere;
use App\Form\AdhesionType;
use App\Repository\AdhesionRepository;
use App\Repository\LicencieRepository;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Licences et suivi des paiements : une fiche par licencié et par saison (règlements
 * échelonnés, remises en banque, aides à recevoir). Les saisons passées restent consultables.
 */
#[Route('/admin/paiements')]
class AdhesionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdhesionRepository $adhesions,
    ) {
    }

    #[Route('', name: 'admin_adhesion_index', methods: ['GET'])]
    public function index(): Response
    {
        $adhesions = $this->adhesions->findAllDetailed();

        return $this->render('admin/adhesion/index.html.twig', [
            'adhesions'    => $adhesions,
            'totalDu'      => array_sum(array_map(static fn (Adhesion $a) => $a->getMontantDuCentimes(), $adhesions)),
            'totalRecu'    => array_sum(array_map(static fn (Adhesion $a) => $a->getRecuCentimes(), $adhesions)),
            'totalAides'   => array_sum(array_map(static fn (Adhesion $a) => $a->getAidesAttenduesCentimes(), $adhesions)),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_adhesion_new', methods: ['GET', 'POST'])]
    public function new(Request $request, LicencieRepository $licencies, SaisonRepository $saisons): Response
    {
        $adhesion = new Adhesion();
        if ($id = $request->query->getInt('licencie')) {
            $adhesion->setLicencie($licencies->find($id));
        }
        $adhesion->setSaison($saisons->findActive());

        $form = $this->createForm(AdhesionType::class, $adhesion, ['nouveau' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->adhesions->findOneBy(['licencie' => $adhesion->getLicencie(), 'saison' => $adhesion->getSaison()])) {
                $form->get('saison')->addError(new FormError('Ce licencié a déjà une licence pour cette saison : ouvrez-la depuis la liste.'));
            } else {
                $this->renumber($adhesion);
                $this->em->persist($adhesion);
                $this->em->flush();
                $this->addFlash('success', 'Licence enregistrée.');

                return $this->redirectToRoute('admin_adhesion_edit', ['id' => $adhesion->getId()]);
            }
        }

        return $this->render('admin/adhesion/form.html.twig', ['form' => $form, 'adhesion' => $adhesion]);
    }

    #[Route('/{id}', name: 'admin_adhesion_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Adhesion $adhesion): Response
    {
        $form = $this->createForm(AdhesionType::class, $adhesion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->renumber($adhesion);
            $this->em->flush();
            $this->addFlash('success', 'Suivi de paiement mis à jour.');

            return $this->redirectToRoute('admin_adhesion_edit', ['id' => $adhesion->getId()]);
        }

        return $this->render('admin/adhesion/form.html.twig', [
            'form'     => $form,
            'adhesion' => $adhesion,
            'history'  => $this->adhesions->forLicencie($adhesion->getLicencie()),
        ]);
    }

    /**
     * Suivi rapide d'une licence (ouvert dans une fenêtre depuis la liste) : étape par étape,
     * on marque les règlements et les aides comme reçus, avec leurs dates et références.
     */
    #[Route('/{id}/suivi', name: 'admin_adhesion_suivi', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function suivi(Request $request, Adhesion $adhesion): Response
    {
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('suivi-adhesion-'.$adhesion->getId(), (string) $request->request->get('_token'))) {
                $data = $request->request->all();
                foreach ($adhesion->getReglements() as $reglement) {
                    $row = $data['reglements'][$reglement->getId()] ?? [];
                    $reglement->setRecu(!empty($row['recu']))
                        ->setDateRemise($this->date($row['dateRemise'] ?? null))
                        ->setReference($this->text($row['reference'] ?? null, 100));
                }
                foreach ($adhesion->getAides() as $aide) {
                    $row = $data['aides'][$aide->getId()] ?? [];
                    $aide->setStatut(!empty($row['recue']) ? AideFinanciere::STATUT_RECUE : AideFinanciere::STATUT_ATTENDUE)
                        ->setDateReception($this->date($row['dateReception'] ?? null))
                        ->setNote($this->text($row['note'] ?? null, 255));
                }
                $adhesion->setObservation($this->text($data['observation'] ?? null, 5000));
                $this->em->flush();
                $this->addFlash('success', 'Suivi enregistré.');
            }

            return $this->redirectToRoute('admin_adhesion_suivi', ['id' => $adhesion->getId()]);
        }

        return $this->render('admin/adhesion/suivi.html.twig', ['adhesion' => $adhesion]);
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return $date && $date->format('Y-m-d') === (string) $value ? $date : null;
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : mb_substr($value, 0, $max);
    }

    #[Route('/{id}/supprimer', name: 'admin_adhesion_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Adhesion $adhesion): Response
    {
        if ($this->isCsrfTokenValid('delete-adhesion-'.$adhesion->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($adhesion);
            $this->em->flush();
            $this->addFlash('success', 'Licence supprimée.');
        }

        return $this->redirectToRoute('admin_adhesion_index');
    }

    /** Les règlements sont numérotés dans l'ordre de saisie : 1, 2, 3… */
    private function renumber(Adhesion $adhesion): void
    {
        $i = 1;
        foreach ($adhesion->getReglements() as $reglement) {
            $reglement->setOrdre($i++);
        }
    }
}

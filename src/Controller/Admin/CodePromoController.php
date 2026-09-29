<?php

namespace App\Controller\Admin;

use App\Entity\CodePromo;
use App\Form\CodePromoType;
use App\Repository\CodePromoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Codes de réduction utilisables au paiement d'une commande boutique. */
#[Route('/admin/codes-promo')]
class CodePromoController extends AbstractController
{
    #[Route('', name: 'admin_code_promo_index', methods: ['GET'])]
    public function index(CodePromoRepository $repository): Response
    {
        return $this->render('admin/code_promo/index.html.twig', [
            'codesPromo' => $repository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_code_promo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $codePromo = new CodePromo();
        $form      = $this->createForm(CodePromoType::class, $codePromo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($em->getRepository(CodePromo::class)->findOneBy(['code' => $codePromo->getCode()])) {
                $this->addFlash('error', sprintf('Le code « %s » existe déjà.', $codePromo->getCode()));
            } else {
                $em->persist($codePromo);
                $em->flush();

                $this->addFlash('success', 'Code de réduction créé.');

                return $this->redirectToRoute('admin_code_promo_index');
            }
        }

        return $this->render('admin/code_promo/form.html.twig', [
            'form'      => $form,
            'codePromo' => $codePromo,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_code_promo_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CodePromo $codePromo, EntityManagerInterface $em): Response
    {
        $ancienCode = $codePromo->getCode();
        $form       = $this->createForm(CodePromoType::class, $codePromo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $conflit = $codePromo->getCode() !== $ancienCode
                && $em->getRepository(CodePromo::class)->findOneBy(['code' => $codePromo->getCode()]);

            if ($conflit) {
                $this->addFlash('error', sprintf('Le code « %s » existe déjà.', $codePromo->getCode()));
            } else {
                $em->flush();

                $this->addFlash('success', 'Code de réduction mis à jour.');

                return $this->redirectToRoute('admin_code_promo_index');
            }
        }

        return $this->render('admin/code_promo/form.html.twig', [
            'form'      => $form,
            'codePromo' => $codePromo,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_code_promo_delete', methods: ['POST'])]
    public function delete(Request $request, CodePromo $codePromo, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-code-promo-' . $codePromo->getId(), (string) $request->request->get('_token'))) {
            $em->remove($codePromo);
            $em->flush();
            $this->addFlash('success', 'Code de réduction supprimé.');
        }

        return $this->redirectToRoute('admin_code_promo_index');
    }
}

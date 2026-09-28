<?php

namespace App\Controller\Admin;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use App\Service\OrdreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Catégories d'encadrement (rattachées aux membres du staff présentés sur le site). */
#[Route('/admin/categories')]
class CategorieController extends AbstractController
{
    #[Route('', name: 'admin_categorie_index', methods: ['GET'])]
    public function index(CategorieRepository $repository): Response
    {
        return $this->render('admin/categorie/index.html.twig', [
            'categories' => $repository->findBy([], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, OrdreService $ordreService): Response
    {
        $categorie = new Categorie();
        $categorie->setOrdre($ordreService->getNextOrdre(Categorie::class));

        $form = $this->createForm(CategorieType::class, $categorie);
        $form->add('nomConfirmation', TextType::class, [
            'mapped'   => false,
            'required' => true,
            'label'    => 'Confirmer le nom',
            'help'     => '⚠️ Saisissez le même nom que ci-dessus pour confirmer la création.',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nom             = trim($categorie->getNom() ?? '');
            $nomConfirmation = trim($form->get('nomConfirmation')->getData() ?? '');

            if (strtolower($nom) !== strtolower($nomConfirmation)) {
                $this->addFlash('error', "Les deux noms ne correspondent pas. La catégorie n'a pas été créée.");
            } else {
                $em->persist($categorie);
                $em->flush();

                $this->addFlash('success', sprintf('Catégorie "%s" créée avec succès.', $nom));

                return $this->redirectToRoute('admin_categorie_index');
            }
        }

        return $this->render('admin/categorie/form.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_categorie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('admin_categorie_index');
        }

        return $this->render('admin/categorie/form.html.twig', [
            'form'      => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/reordonner', name: 'admin_categorie_reorder', methods: ['POST'])]
    public function reorder(Request $request, CategorieRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('reorder-categorie', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Jeton invalide.'], 403);
        }

        $categories = $repository->findBy(['id' => $data['ids'] ?? []]);
        $byId       = [];
        foreach ($categories as $categorie) {
            $byId[$categorie->getId()] = $categorie;
        }

        foreach (array_values($data['ids'] ?? []) as $index => $id) {
            $byId[$id]?->setOrdre($index);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/supprimer', name: 'admin_categorie_delete', methods: ['POST'])]
    public function delete(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-categorie-'.$categorie->getId(), $request->request->get('_token'))) {
            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_categorie_index');
    }
}

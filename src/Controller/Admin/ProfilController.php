<?php

namespace App\Controller\Admin;

use App\Entity\ProfilAutorisation;
use App\Form\ProfilAutorisationType;
use App\Repository\ProfilAutorisationRepository;
use App\Repository\UserRepository;
use App\Security\ProfilDefaults;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Profils d'autorisation : des ensembles d'autorisations prédéfinis, à attribuer à des utilisateurs.
 * Les autorisations exigées par chaque route sont appliquées par PermissionListener.
 */
#[Route('/admin/autorisations')]
class ProfilController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProfilAutorisationRepository $profils,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'admin_profil_index', methods: ['GET'])]
    public function index(ProfilDefaults $defaults): Response
    {
        $defaults->seedIfEmpty();

        $profils  = $this->profils->findBy([], ['nom' => 'ASC']);
        $membres  = [];
        foreach ($profils as $profil) {
            $membres[$profil->getId()] = $this->users->count(['profil' => $profil]);
        }

        return $this->render('admin/acces/profil_index.html.twig', ['profils' => $profils, 'membres' => $membres]);
    }

    #[Route('/nouveau', name: 'admin_profil_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $profil = new ProfilAutorisation();
        // Duplication : ?copie=<id> part des autorisations d'un profil existant.
        if ($source = $this->profils->find($request->query->getInt('copie'))) {
            $profil->setPermissions($source->getPermissions())->setDescription($source->getDescription());
        }

        return $this->handle($request, $profil, 'Profil créé.');
    }

    #[Route('/{id}/modifier', name: 'admin_profil_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ProfilAutorisation $profil): Response
    {
        return $this->handle($request, $profil, 'Profil mis à jour.');
    }

    #[Route('/{id}/supprimer', name: 'admin_profil_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, ProfilAutorisation $profil): Response
    {
        if ($this->isCsrfTokenValid('delete-profil-'.$profil->getId(), (string) $request->request->get('_token'))) {
            $utilisateurs = $this->users->findBy(['profil' => $profil]);
            // Un utilisateur qui perd son profil garde exactement les mêmes autorisations, à titre personnel.
            foreach ($utilisateurs as $user) {
                $user->setPermissionsAjoutees(array_values(array_unique(array_merge(
                    array_diff($profil->getPermissions(), $user->getPermissionsRetirees()),
                    $user->getPermissionsAjoutees(),
                ))))->setPermissionsRetirees([])->setAccesRestreint(true)->setProfil(null);
            }
            $this->em->remove($profil);
            $this->em->flush();
            $this->addFlash('success', 'Profil supprimé.'.($utilisateurs ? sprintf(' %d utilisateur(s) conservent leurs autorisations à titre personnel.', count($utilisateurs)) : ''));
        }

        return $this->redirectToRoute('admin_profil_index');
    }

    private function handle(Request $request, ProfilAutorisation $profil, string $message): Response
    {
        $form = $this->createForm(ProfilAutorisationType::class, $profil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profil->setPermissions($request->request->all('permissions'));
            $this->em->persist($profil);
            $this->em->flush();
            $this->addFlash('success', $message);

            return $this->redirectToRoute('admin_profil_index');
        }

        $selected = $form->isSubmitted() ? $request->request->all('permissions') : $profil->getPermissions();

        return $this->render('admin/acces/profil_form.html.twig', ['form' => $form, 'profil' => $profil, 'selected' => $selected]);
    }
}

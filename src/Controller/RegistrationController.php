<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Legal\LegalVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création libre d'un compte « boutique » : n'importe qui peut s'inscrire, sans lien avec une
 * famille, un licencié ou un compte du back-office. Ce compte ne donne accès qu'à la boutique
 * et à son propre historique de commandes (voir PortailCommandeController, PortailExtension).
 */
class RegistrationController extends AbstractController
{
    #[Route('/mon-compte/inscription', name: 'portail_inscription', methods: ['GET', 'POST'])]
    public function inscription(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('portail_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->recordConsent(LegalVersion::CURRENT);
            $user->setRoles([]); // compte boutique : aucun rôle particulier, seulement ROLE_USER (implicite)
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('portail_login');
        }

        return $this->render('portail/inscription.html.twig', ['form' => $form]);
    }
}

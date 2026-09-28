<?php

namespace App\Controller\Admin;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Form\LicencieType;
use App\Repository\AdhesionRepository;
use App\Repository\LicencieRepository;
use App\Repository\UserRepository;
use App\Service\AccountActivationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/licencies')]
class LicencieController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly AccountActivationMailer $activationMailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'admin_licencie_index', methods: ['GET'])]
    public function index(LicencieRepository $repository): Response
    {
        return $this->render('admin/licencie/index.html.twig', [
            'licencies' => $repository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    /** Fiche en lecture seule : toutes les informations, sans possibilité de les modifier. */
    #[Route('/{id}', name: 'admin_licencie_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Licencie $licencie, AdhesionRepository $adhesions): Response
    {
        return $this->render('admin/licencie/show.html.twig', ['licencie' => $licencie, 'adhesions' => $adhesions->forLicencie($licencie)]);
    }

    #[Route('/nouveau', name: 'admin_licencie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $licencie = new Licencie();
        $form     = $this->createForm(LicencieType::class, $licencie, ['nouveau' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email     = strtolower(trim((string) $form->get('email')->getData()));
            $autonome  = (bool) $form->get('autonome')->getData();

            if ($this->users->findOneBy(['email' => $email])) {
                $form->get('email')->addError(new FormError('Cette adresse est déjà utilisée par un autre compte.'));
            } elseif (!$autonome && !$licencie->getFamille()) {
                $form->get('famille')->addError(new FormError('Choisissez une famille, ou cochez « gère lui-même son compte ».'));
            } else {
                $user = (new User())
                    ->setEmail($email)
                    ->setNom((string) $licencie->getNom())
                    ->setPrenom($licencie->getPrenom())
                    ->setRoles($autonome ? ['ROLE_LICENCIE', 'ROLE_FAMILLE'] : ['ROLE_LICENCIE']);
                $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(16))));
                $licencie->setUser($user);

                if ($autonome) {
                    // Chef de sa propre famille : la famille partage son compte.
                    $famille = (new Famille())->setNom((string) $licencie->getNom())->setUser($user);
                    $this->em->persist($famille);
                    $licencie->setFamille($famille);
                }

                $this->em->persist($licencie);
                $this->em->flush();

                $this->flashAccess($user, 'Licencié créé.');

                return $this->redirectToRoute('admin_licencie_index');
            }
        }

        return $this->render('admin/licencie/form.html.twig', ['form' => $form, 'licencie' => $licencie]);
    }

    #[Route('/{id}/modifier', name: 'admin_licencie_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Licencie $licencie): Response
    {
        $form = $this->createForm(LicencieType::class, $licencie, ['email' => $licencie->getUser()?->getEmail()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));
            $other = $this->users->findOneBy(['email' => $email]);

            if ($other && $other !== $licencie->getUser()) {
                $form->get('email')->addError(new FormError('Cette adresse est déjà utilisée par un autre compte.'));
            } elseif (!$licencie->getFamille()) {
                $form->get('famille')->addError(new FormError('Un licencié doit appartenir à une famille.'));
            } else {
                $licencie->getUser()->setEmail($email);
                $this->em->flush();
                $this->addFlash('success', 'Licencié mis à jour.');

                return $this->redirectToRoute('admin_licencie_index');
            }
        }

        return $this->render('admin/licencie/form.html.twig', ['form' => $form, 'licencie' => $licencie]);
    }

    /** Renvoie l'e-mail « créez votre mot de passe » au licencié (accès perdu, adresse corrigée…). */
    #[Route('/{id}/renvoyer-acces', name: 'admin_licencie_resend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendAccess(Request $request, Licencie $licencie): Response
    {
        if ($this->isCsrfTokenValid('resend-licencie-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            $user = $licencie->getUser();
            if (str_ends_with((string) $user?->getEmail(), '@import.local')) {
                $this->addFlash('error', sprintf('%s n\'a pas d\'adresse e-mail réelle (adresse provisoire d\'import). Renseignez-la dans la fiche, puis renvoyez l\'accès.', $licencie));
            } else {
                $this->flashAccess($user, null, $licencie);
            }
        }

        return $this->redirectToRoute('admin_licencie_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_licencie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Licencie $licencie): Response
    {
        $ajax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('delete-licencie-'.$licencie->getId(), (string) $request->request->get('_token'))) {
            return $ajax
                ? new JsonResponse(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN)
                : $this->redirectToRoute('admin_licencie_index');
        }

        $nom = (string) $licencie;

        // Licencié autonome : sa famille n'existe que pour lui, elle part avec lui.
        if ($licencie->isAutonome() && 1 === $licencie->getFamille()->getLicencies()->count()) {
            $this->em->remove($licencie->getFamille());
        }
        $this->em->remove($licencie);
        $this->em->flush();

        if ($ajax) {
            return new JsonResponse(['ok' => true, 'message' => sprintf('%s a été supprimé.', $nom)]);
        }
        $this->addFlash('success', 'Licencié supprimé.');

        return $this->redirectToRoute('admin_licencie_index');
    }

    /** Envoie l'e-mail d'activation et prévient l'admin du résultat (sans jamais bloquer l'enregistrement). */
    private function flashAccess(User $user, ?string $prefix, ?Licencie $licencie = null): void
    {
        try {
            $this->activationMailer->sendActivationEmail($user);
            $this->addFlash('success', ($prefix ? $prefix.' ' : '').sprintf('Un e-mail pour créer son mot de passe a été envoyé à %s.', $user->getEmail()));
        } catch (\Throwable $e) {
            $this->logger->error('Envoi de l\'e-mail d\'activation impossible : '.$e->getMessage());
            $this->addFlash('error', ($prefix ? $prefix.' ' : '').'L\'e-mail n\'a pas pu être envoyé : vérifiez la configuration de la messagerie, puis renvoyez l\'accès depuis la liste.');
        }
    }
}

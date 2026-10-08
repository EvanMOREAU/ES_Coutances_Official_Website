<?php

namespace App\Controller\Admin;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Form\FamilleType;
use App\Form\FamilleWizardType;
use App\Repository\FamilleRepository;
use App\Repository\UserRepository;
use App\Service\AccountActivationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/familles')]
class FamilleController extends AbstractController
{
    #[Route('', name: 'admin_famille_index', methods: ['GET'])]
    public function index(FamilleRepository $repository): Response
    {
        return $this->render('admin/famille/index.html.twig', [
            'familles' => $repository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_famille_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        AccountActivationMailer $activationMailer,
    ): Response {
        $form = $this->createForm(FamilleWizardType::class, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data      = $form->getData();
            $licencies = $data['licencies'] ?? [];

            // On vérifie TOUTES les adresses (famille + chaque licencié) avant de
            // rien créer, pour ne jamais laisser une famille à moitié créée.
            $emails = array_filter(array_merge([$data['email']], array_column($licencies, 'email')));
            $doublons = [];
            foreach (array_count_values($emails) as $email => $count) {
                if ($count > 1) {
                    $doublons[] = $email;
                }
            }
            foreach ($emails as $email) {
                if ($userRepository->findOneBy(['email' => $email])) {
                    $doublons[] = $email;
                }
            }

            if (!empty($doublons)) {
                $this->addFlash('error', sprintf('Adresse(s) déjà utilisée(s) : %s', implode(', ', array_unique($doublons))));
            } else {
                $familleUser = new User();
                $familleUser->setEmail($data['email']);
                $familleUser->setNom($data['nom']);
                $familleUser->setPrenom($data['prenomReferent'] ?: null);
                $familleUser->setRoles(['ROLE_FAMILLE']);
                $familleUser->setPassword($hasher->hashPassword($familleUser, bin2hex(random_bytes(16))));
                $em->persist($familleUser);

                $famille = new Famille();
                $famille->setNom($data['nom']);
                $famille->setUser($familleUser);
                $famille->setAdresse($data['adresse'] ?: null);
                $famille->setCodePostal($data['codePostal'] ?: null);
                $famille->setVille($data['ville'] ?: null);
                $famille->setTelephone($data['telephone'] ?: null);
                $em->persist($famille);

                $licencieUsers = [$familleUser];
                foreach ($licencies as $row) {
                    $licencieUser = new User();
                    $licencieUser->setEmail($row['email']);
                    $licencieUser->setPrenom($row['prenom']);
                    $licencieUser->setNom($row['nom']);
                    $licencieUser->setRoles(['ROLE_LICENCIE']);
                    $licencieUser->setPassword($hasher->hashPassword($licencieUser, bin2hex(random_bytes(16))));
                    $em->persist($licencieUser);

                    $licencie = new Licencie();
                    $licencie->setNom($row['nom']);
                    $licencie->setPrenom($row['prenom']);
                    $licencie->setDateNaissance($row['dateNaissance']);
                    $licencie->setSaison($row['saison']);
                    $licencie->setFamille($famille);
                    $licencie->setUser($licencieUser);
                    $licencie->setDecalageCategorie((int) $row['decalageCategorie']);
                    foreach ($row['equipes'] as $equipe) {
                        $licencie->addEquipe($equipe);
                    }
                    $em->persist($licencie);

                    $licencieUsers[] = $licencieUser;
                }

                $em->flush();

                foreach ($licencieUsers as $u) {
                    $activationMailer->sendActivationEmail($u);
                }

                $this->addFlash('success', sprintf(
                    'Famille "%s" créée avec %d licencié(s). Un email de définition de mot de passe a été envoyé à chaque compte créé.',
                    $famille->getNom(),
                    count($licencies),
                ));

                return $this->redirectToRoute('admin_famille_show', ['id' => $famille->getId()]);
            }
        }

        return $this->render('admin/famille/wizard.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_famille_show', methods: ['GET'])]
    public function show(Famille $famille): Response
    {
        return $this->render('admin/famille/show.html.twig', [
            'famille' => $famille,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_famille_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Famille $famille, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $form = $this->createForm(FamilleType::class, $famille);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $conflict = $userRepository->createQueryBuilder('u')
                ->andWhere('u.email = :email')
                ->andWhere('u.id != :id')
                ->setParameter('email', $famille->getUser()->getEmail())
                ->setParameter('id', $famille->getUser()->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($conflict) {
                $this->addFlash('error', 'Un autre compte utilise déjà cette adresse email.');
            } else {
                $em->flush();

                $this->addFlash('success', 'Famille mise à jour.');

                return $this->redirectToRoute('admin_famille_index');
            }
        }

        return $this->render('admin/famille/form.html.twig', [
            'form'    => $form,
            'famille' => $famille,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_famille_delete', methods: ['POST'])]
    public function delete(Request $request, Famille $famille, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-famille-'.$famille->getId(), $request->request->get('_token'))) {
            if (!$famille->getLicencies()->isEmpty()) {
                $this->addFlash('error', "Cette famille contient encore des licenciés : supprimez-les d'abord un par un.");

                return $this->redirectToRoute('admin_famille_index');
            }

            $em->remove($famille);
            $em->flush();
            $this->addFlash('success', 'Famille supprimée.');
        }

        return $this->redirectToRoute('admin_famille_index');
    }
}

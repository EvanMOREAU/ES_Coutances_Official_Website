<?php

namespace App\Controller;

use App\Entity\Licencie;
use App\Entity\User;
use App\Repository\EntrainementRepository;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Security\LicencieVoter;
use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/mon-compte')]
class PortailController extends AbstractController
{
    #[Route('/connexion', name: 'portail_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('portail_index');
        }

        return $this->render('portail/login.html.twig', [
            'error'         => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route('/deconnexion', name: 'portail_logout')]
    public function logout(): void
    {
        // intercepté par Symfony Security, ce code ne s'exécute jamais
    }

    #[Route('', name: 'portail_index')]
    public function index(
        FamilleRepository $familleRepository,
        LicencieRepository $licencieRepository,
        EntrainementRepository $entrainementRepository,
        PlanningService $planning,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $famille  = $familleRepository->findOneBy(['user' => $user]);
        $licencie = $licencieRepository->findOneBy(['user' => $user]);

        // Prochains entraînements et rencontres qui concernent leurs licenciés.
        $licencies     = $planning->licenciesFor($user);
        $entrainements = [];
        if ([] !== $licencies) {
            $entrainements = array_slice(array_values(array_filter(
                $entrainementRepository->between(new \DateTimeImmutable('today'), new \DateTimeImmutable('+60 days')),
                static fn ($e) => $planning->concerns($e, $licencies),
            )), 0, 5);
        }

        return $this->render('portail/index.html.twig', [
            'famille'       => $famille,
            'licencie'      => $licencie,
            'entrainements' => $entrainements,
        ]);
    }

    #[Route('/licencie/{id}', name: 'portail_licencie')]
    public function licencie(Licencie $licencie): Response
    {
        $this->denyAccessUnlessGranted(LicencieVoter::VIEW, $licencie);

        return $this->render('portail/licencie.html.twig', [
            'licencie' => $licencie,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Privacy\AccountDataService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Confidentialité (RGPD) de l'espace « Mon compte » : récapitulatif téléchargeable des données du compte,
 * et suppression du compte avec anonymisation (les factures du club sont conservées).
 */
#[Route('/mon-compte/parametres/confidentialite')]
#[IsGranted('ROLE_USER')]
class AccountPrivacyController extends AbstractController
{
    #[Route('', name: 'portail_parametres_confidentialite', methods: ['GET'])]
    public function index(AccountDataService $data): Response
    {
        return $this->render('portail/parametres/confidentialite.html.twig', [
            'active_tab'    => 'confidentialite',
            'can_anonymize' => $data->canAnonymize($this->me()),
        ]);
    }

    /** Téléchargement des données : version lisible/imprimable (HTML) ou exploitable par une machine (JSON). */
    #[Route('/export', name: 'portail_parametres_export', methods: ['GET'])]
    public function export(Request $request, AccountDataService $data): Response
    {
        $user    = $this->me();
        $content = $data->export($user);
        $format  = $request->query->get('format') === 'json' ? 'json' : 'html';
        $name    = sprintf('mes-donnees-es-coutances-%s.%s', date('Y-m-d'), $format);

        $response = $format === 'json'
            ? new Response(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json; charset=UTF-8'])
            : $this->render('portail/export.html.twig', ['data' => $content, 'user' => $user]);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/supprimer', name: 'portail_parametres_supprimer', methods: ['POST'])]
    public function delete(
        Request $request,
        AccountDataService $data,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
        LoggerInterface $logger,
        string $mailerFromAddress,
        string $mailerFromName,
    ): Response {
        $user = $this->me();
        if (!$this->isCsrfTokenValid('account_delete', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$data->canAnonymize($user)) {
            $this->addFlash('error', 'Ce compte ne peut pas être supprimé ici. Contactez le club.');

            return $this->redirectToRoute('portail_parametres_confidentialite');
        }
        if (!$hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'Mot de passe incorrect : votre compte n\'a pas été supprimé.');

            return $this->redirectToRoute('portail_parametres_confidentialite');
        }
        if (mb_strtoupper(trim((string) $request->request->get('confirmation'))) !== 'SUPPRIMER') {
            $this->addFlash('error', 'Tapez « SUPPRIMER » pour confirmer la suppression de votre compte.');

            return $this->redirectToRoute('portail_parametres_confidentialite');
        }

        // Confirmation par e-mail, envoyée avant que l'adresse ne soit effacée.
        $address = (string) $user->getEmail();
        $name    = $user->getPrenom() ?: $user->getNom();
        try {
            $mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($mailerFromAddress, $mailerFromName))
                    ->to($address)
                    ->subject('Votre compte ES Coutances a été supprimé')
                    ->htmlTemplate('email/account_deleted.html.twig')
                    ->context(['name' => $name]),
            );
        } catch (\Throwable $e) {
            $logger->warning('E-mail de confirmation de suppression de compte non envoyé : '.$e->getMessage());
        }

        $data->anonymize($user);

        // La session ne correspond plus à rien : on la ferme et on affiche la confirmation.
        $this->container->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        return $this->render('portail/compte_supprime.html.twig');
    }

    private function me(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}

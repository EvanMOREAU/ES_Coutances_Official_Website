<?php

namespace App\Controller\Security;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Actions complémentaires à l'écran de saisie du second facteur (fourni par
 * scheb/2fa-bundle) : renvoi du code par e-mail à la demande de l'utilisateur.
 */
class TwoFactorController extends AbstractController
{
    #[Route('/admin/2fa/resend-email', name: '2fa_resend_email', methods: ['POST'])]
    public function resendEmail(Request $request, Security $security, CodeGeneratorInterface $codeGenerator): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('2fa_resend_email', (string) $request->request->get('_csrf_token'))) {
            return $this->redirectToRoute('2fa_login');
        }

        $user = $security->getUser();
        if ($user instanceof User && $user->isEmailAuthEnabled()) {
            $codeGenerator->generateAndSend($user);
            $this->addFlash('success', 'Un nouveau code vous a été envoyé par e-mail.');
        }

        return $this->redirectToRoute('2fa_login');
    }
}

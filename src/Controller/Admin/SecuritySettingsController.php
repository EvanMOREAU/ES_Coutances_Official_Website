<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use App\Security\TwoFactor\PendingTotpSecret;
use App\Service\Webauthn\WebauthnService;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sécurité du compte connecté : clés d'accès (passkeys), application d'authentification (TOTP),
 * code par e-mail et codes de secours. Les routes sont communes à l'administration et à l'espace
 * « Mon compte » (voir AbstractAccountController).
 */
#[IsGranted('ROLE_USER')]
class SecuritySettingsController extends AbstractAccountController
{
    #[Route('/admin/parametres/securite', name: 'admin_parametres_securite', methods: ['GET'])]
    #[Route('/mon-compte/parametres/securite', name: 'portail_parametres_securite', methods: ['GET'])]
    public function securite(Request $request, TotpAuthenticatorInterface $totpAuthenticator, WebauthnCredentialRepository $webauthnCredentials): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $session = $request->getSession();

        $pendingTotpSecret = $session->get('totp_pending_secret');
        $totpQrDataUri = null;
        if (is_string($pendingTotpSecret)) {
            $pending = new PendingTotpSecret((string) $user->getEmail(), $pendingTotpSecret);
            $totpQrDataUri = (new Builder())
                ->build(data: $totpAuthenticator->getQRContent($pending), size: 220, margin: 10)
                ->getDataUri();
        }

        $newBackupCodes = $session->get('new_backup_codes');
        $session->remove('new_backup_codes');

        return $this->render($this->tpl($request, 'securite'), [
            'active_tab'         => 'securite',
            'user'               => $user,
            'pendingTotpSecret'  => $pendingTotpSecret,
            'totpQrDataUri'      => $totpQrDataUri,
            'newBackupCodes'     => $newBackupCodes,
            'passkeys'           => $webauthnCredentials->findAllForUser($user),
        ]);
    }

    // --- Clés d'accès (passkeys) --------------------------------------------

    #[Route('/admin/parametres/securite/webauthn/options', name: 'admin_parametres_securite_webauthn_options', methods: ['GET'])]
    #[Route('/mon-compte/parametres/securite/webauthn/options', name: 'portail_parametres_securite_webauthn_options', methods: ['GET'])]
    public function webauthnOptions(WebauthnService $webauthn): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $options = $webauthn->generateRegistrationOptions($user);

        return JsonResponse::fromJsonString($webauthn->optionsToJson($options));
    }

    #[Route('/admin/parametres/securite/webauthn/enregistrer', name: 'admin_parametres_securite_webauthn_enregistrer', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/webauthn/enregistrer', name: 'portail_parametres_securite_webauthn_enregistrer', methods: ['POST'])]
    public function webauthnEnregistrer(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('securite_webauthn_enregistrer', (string) ($data['_csrf_token'] ?? ''))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $label = is_string($data['label'] ?? null) ? $data['label'] : '';

        try {
            $webauthn->verifyRegistration($user, json_encode($data['response'] ?? null), $label);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => "L'enregistrement de la clé d'accès a échoué : ".$e->getMessage()], 422);
        }

        $this->addFlash('success', 'Clé d\'accès enregistrée.');

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/admin/parametres/securite/webauthn/{id}/supprimer', name: 'admin_parametres_securite_webauthn_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/webauthn/{id}/supprimer', name: 'portail_parametres_securite_webauthn_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function webauthnSupprimer(Request $request, int $id, WebauthnCredentialRepository $webauthnCredentials, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('securite_webauthn_supprimer', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $credential = $webauthnCredentials->find($id);
        if ($credential && $credential->getUser() === $user) {
            $em->remove($credential);
            $em->flush();
            $this->addFlash('success', 'Clé d\'accès supprimée.');
        }

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/totp/generer', name: 'admin_parametres_securite_totp_generer', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/totp/generer', name: 'portail_parametres_securite_totp_generer', methods: ['POST'])]
    public function totpGenerer(Request $request, TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        if (!$this->isCsrfTokenValid('securite_totp_generer', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        $request->getSession()->set('totp_pending_secret', $totpAuthenticator->generateSecret());

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/totp/confirmer', name: 'admin_parametres_securite_totp_confirmer', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/totp/confirmer', name: 'portail_parametres_securite_totp_confirmer', methods: ['POST'])]
    public function totpConfirmer(Request $request, TotpAuthenticatorInterface $totpAuthenticator, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('securite_totp_confirmer', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $session = $request->getSession();
        $pendingSecret = $session->get('totp_pending_secret');
        $code = (string) $request->request->get('code');

        if (!is_string($pendingSecret)) {
            $this->addFlash('error', "Aucune activation en cours. Recommencez l'opération.");

            return $this->to($request, 'securite');
        }

        $pending = new PendingTotpSecret((string) $user->getEmail(), $pendingSecret);
        if (!$totpAuthenticator->checkCode($pending, $code)) {
            $this->addFlash('error', 'Code invalide. Vérifiez l\'heure de votre appareil et réessayez.');

            return $this->to($request, 'securite');
        }

        $user->setTotpSecret($pendingSecret);
        $session->remove('totp_pending_secret');
        $em->flush();
        $this->addFlash('success', "L'application d'authentification est activée.");

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/totp/annuler', name: 'admin_parametres_securite_totp_annuler', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/totp/annuler', name: 'portail_parametres_securite_totp_annuler', methods: ['POST'])]
    public function totpAnnuler(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('securite_totp_annuler', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        $request->getSession()->remove('totp_pending_secret');

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/totp/desactiver', name: 'admin_parametres_securite_totp_desactiver', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/totp/desactiver', name: 'portail_parametres_securite_totp_desactiver', methods: ['POST'])]
    public function totpDesactiver(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('securite_totp_desactiver', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setTotpSecret(null);
        $em->flush();
        $this->addFlash('success', "L'application d'authentification est désactivée.");

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/email/activer', name: 'admin_parametres_securite_email_activer', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/email/activer', name: 'portail_parametres_securite_email_activer', methods: ['POST'])]
    public function emailActiver(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('securite_email_activer', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setEmailAuthEnabled(true);
        $em->flush();
        $this->addFlash('success', 'Le code de vérification par e-mail est activé.');

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/email/desactiver', name: 'admin_parametres_securite_email_desactiver', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/email/desactiver', name: 'portail_parametres_securite_email_desactiver', methods: ['POST'])]
    public function emailDesactiver(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('securite_email_desactiver', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setEmailAuthEnabled(false);
        $em->flush();
        $this->addFlash('success', 'Le code de vérification par e-mail est désactivé.');

        return $this->to($request, 'securite');
    }

    #[Route('/admin/parametres/securite/codes-secours/generer', name: 'admin_parametres_securite_backup_codes_generer', methods: ['POST'])]
    #[Route('/mon-compte/parametres/securite/codes-secours/generer', name: 'portail_parametres_securite_backup_codes_generer', methods: ['POST'])]
    public function backupCodesGenerer(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('securite_backup_codes_generer', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();

        $plainCodes = [];
        for ($i = 0; $i < 10; ++$i) {
            $plainCodes[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
        }

        $user->setBackupCodes(array_map(static fn (string $code): string => password_hash($code, PASSWORD_DEFAULT), $plainCodes));
        $em->flush();

        $request->getSession()->set('new_backup_codes', $plainCodes);
        $this->addFlash('success', 'De nouveaux codes de secours ont été générés. Notez-les, ils ne seront plus affichés.');

        return $this->to($request, 'securite');
    }
}

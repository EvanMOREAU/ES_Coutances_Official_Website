<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AppearanceType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use App\Service\Chat\ChatService;
use App\Service\Notification\NotificationPreferences;
use App\Repository\WebauthnCredentialRepository;
use App\Security\TwoFactor\PendingTotpSecret;
use App\Service\Webauthn\WebauthnService;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Paramètres personnels du compte connecté (profil, sécurité, apparence,
 * préférences de notification) — distinct de "Réglages" qui configure le
 * site du club, accessible à tous les utilisateurs authentifiés.
 */
#[IsGranted('ROLE_USER')]
class AccountSettingsController extends AbstractController
{
    /** Les mêmes réglages servent l'administration et l'espace « Mon compte » : deux séries de routes, deux habillages. */
    private function zone(Request $request): string
    {
        return str_starts_with((string) $request->attributes->get('_route'), 'portail_') ? 'portail' : 'admin';
    }

    private function to(Request $request, string $page): Response
    {
        return $this->redirectToRoute($this->zone($request).'_parametres_'.$page);
    }

    private function tpl(Request $request, string $page): string
    {
        return $this->zone($request).'/parametres/'.$page.'.html.twig';
    }

    #[Route('/admin/parametres/profil', name: 'admin_parametres_profil', methods: ['GET', 'POST'])]
    #[Route('/mon-compte/parametres/profil', name: 'portail_parametres_profil', methods: ['GET', 'POST'])]
    public function profil(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        ChatService $chat,
    ): Response {
        /** @var User $user */
        $user   = $this->getUser();
        $portal = 'portail' === $this->zone($request);
        $client = $portal && $chat->isClient($user);
        $oldEmail = (string) $user->getEmail();

        $form = $this->createForm(ProfileType::class, $user, [
            'with_avatar'      => !$client,
            'with_bio'         => !$portal,
            'confirm_password' => $portal,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $portal && $user->getEmail() !== $oldEmail
            && !$hasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
            // Changer d'adresse exige le mot de passe actuel : on annule la modification en mémoire.
            $user->setEmail($oldEmail);
            $this->addFlash('error', 'Pour changer d\'adresse e-mail, saisissez votre mot de passe actuel.');
        } elseif ($form->isSubmitted() && $form->isValid()) {
            $conflict = $userRepository->createQueryBuilder('u')
                ->andWhere('u.email = :email')
                ->andWhere('u.id != :id')
                ->setParameter('email', $user->getEmail())
                ->setParameter('id', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($conflict) {
                $this->addFlash('error', 'Un autre compte utilise déjà cette adresse email.');
            } else {
                $em->flush();
                $this->addFlash('success', 'Profil mis à jour.');

                return $this->to($request, 'profil');
            }
        }

        $passwordForm = $this->container->get('form.factory')->createNamedBuilder('password_change', FormType::class, null, ['csrf_token_id' => 'change_password'])
            ->add('currentPassword', PasswordType::class, [
                'label'       => 'Mot de passe actuel',
                'constraints' => [new NotBlank(message: 'Veuillez saisir votre mot de passe actuel.')],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'first_options'   => ['label' => 'Nouveau mot de passe'],
                'second_options'  => ['label' => 'Confirmer le nouveau mot de passe'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints'     => [new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')],
            ])
            ->add('save', SubmitType::class, ['label' => 'Mettre à jour le mot de passe'])
            ->getForm();
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            if (!$hasher->isPasswordValid($user, $passwordForm->get('currentPassword')->getData())) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            } else {
                $user->setPassword($hasher->hashPassword($user, $passwordForm->get('newPassword')->getData()));
                $em->flush();
                $this->addFlash('success', 'Votre mot de passe a bien été mis à jour.');

                return $this->to($request, 'profil');
            }
        }

        return $this->render($this->tpl($request, 'profil'), [
            'form'          => $form,
            'passwordForm'  => $passwordForm,
            'active_tab'    => 'profil',
            'is_client'     => $client,
        ]);
    }

    #[Route('/admin/parametres/apparence', name: 'admin_parametres_apparence', methods: ['GET', 'POST'])]
    #[Route('/mon-compte/parametres/apparence', name: 'portail_parametres_apparence', methods: ['GET', 'POST'])]
    public function apparence(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(AppearanceType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Préférences d\'apparence mises à jour.');

            return $this->to($request, 'apparence');
        }

        return $this->render($this->tpl($request, 'apparence'), [
            'form'       => $form,
            'active_tab' => 'apparence',
        ]);
    }

    #[Route('/admin/parametres/preferences', name: 'admin_parametres_preferences', methods: ['GET', 'POST'])]
    #[Route('/mon-compte/parametres/preferences', name: 'portail_parametres_preferences', methods: ['GET', 'POST'])]
    public function preferences(Request $request, EntityManagerInterface $em, NotificationPreferences $prefs): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_notification_prefs', (string) $request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException();
            }
            $prefs->save($user, (array) $request->request->all('prefs'));
            $em->flush();
            $this->addFlash('success', 'Préférences de notification mises à jour.');

            return $this->to($request, 'preferences');
        }

        return $this->render($this->tpl($request, 'preferences'), [
            'prefs'      => $prefs,
            'sections'   => $prefs->availableFor($user),
            'active_tab' => 'preferences',
        ]);
    }

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

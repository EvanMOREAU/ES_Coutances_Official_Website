<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AppearanceType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use App\Service\Chat\ChatService;
use App\Service\Locale\Locales;
use App\Service\Notification\NotificationMailer;
use App\Service\Notification\NotificationPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Paramètres personnels du compte connecté (profil, sécurité, apparence,
 * préférences de notification) — distinct de "Réglages" qui configure le
 * site du club, accessible à tous les utilisateurs authentifiés.
 */
#[IsGranted('ROLE_USER')]
class AccountSettingsController extends AbstractAccountController
{
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

            // La langue choisie s'applique dès la page suivante (et se retient pour la page de connexion).
            $response = $this->to($request, 'apparence');
            $response->headers->setCookie(Cookie::create(Locales::COOKIE, $user->getLocale(), new \DateTimeImmutable('+1 year'), '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));

            return $response;
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

    /** E-mail de test à soi-même : permet de vérifier que le serveur d'envoi fonctionne et que la boîte reçoit bien les notifications. */
    #[Route('/admin/parametres/preferences/test', name: 'admin_parametres_preferences_test', methods: ['POST'])]
    #[Route('/mon-compte/parametres/preferences/test', name: 'portail_parametres_preferences_test', methods: ['POST'])]
    public function testEmail(Request $request, NotificationMailer $notifications, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('account_notification_test', (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }
        /** @var User $user */
        $user   = $this->getUser();
        $locale = Locales::normalize($user->getLocale());

        try {
            $notifications->send(
                $user,
                $translator->trans('notif.mail.test_title', [], null, $locale),
                [$translator->trans('notif.mail.test_line', [], null, $locale)],
                $this->generateUrl($this->zone($request).'_parametres_preferences', [], UrlGeneratorInterface::ABSOLUTE_URL),
                $translator->trans('notif.page.title', [], null, $locale),
            );
            $this->addFlash('success', $translator->trans('notif.page.test_sent', ['%email%' => (string) $user->getEmail()]));
        } catch (\Throwable $e) {
            $this->addFlash('error', $translator->trans('notif.page.test_failed', ['%error%' => $e->getMessage()]));
        }

        return $this->to($request, 'preferences');
    }
}

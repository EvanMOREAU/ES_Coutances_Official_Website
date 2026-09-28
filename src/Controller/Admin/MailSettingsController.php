<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\MailSettingsType;
use App\Mail\DynamicMailer;
use App\Mail\SecretBox;
use App\Repository\MailSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres d'envoi des e-mails : serveur SMTP, identifiants et expéditeur, enregistrés en base
 * (mot de passe chiffré). Sans réglage activé, l'application utilise MAILER_DSN.
 */
#[Route('/admin/parametres-mail')]
class MailSettingsController extends AbstractController
{
    public function __construct(
        private readonly MailSettingsRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly SecretBox $secretBox,
    ) {
    }

    #[Route('', name: 'admin_mail_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $settings = $this->repository->getSingleton();
        $form     = $this->createForm(MailSettingsType::class, $settings, ['a_un_mot_de_passe' => $settings->hasPassword()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($settings->isActif() && '' === trim((string) $settings->getHost())) {
                $form->get('host')->addError(new \Symfony\Component\Form\FormError('Indiquez le serveur SMTP pour activer ce réglage.'));
            } else {
                $password = (string) $form->get('password')->getData();
                if ($form->get('retirerMotDePasse')->getData()) {
                    $settings->setPasswordChiffre(null);
                } elseif ('' !== $password) {
                    $settings->setPasswordChiffre($this->secretBox->encrypt($password));
                }
                $settings->touch();
                $this->em->persist($settings);
                $this->em->flush();
                $this->addFlash('success', 'Paramètres e-mail enregistrés.'.($settings->isActif() ? ' Envoyez un e-mail de test pour vérifier la connexion.' : ''));

                return $this->redirectToRoute('admin_mail_settings');
            }
        }

        $user = $this->getUser();

        return $this->render('admin/mail/settings.html.twig', [
            'form'     => $form,
            'settings' => $settings,
            'testTo'   => $user instanceof User ? $user->getEmail() : '',
        ]);
    }

    #[Route('/test', name: 'admin_mail_test', methods: ['POST'])]
    public function test(Request $request, DynamicMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('mail-test', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée : jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_mail_settings');
        }

        $to = trim((string) $request->request->get('to'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Indiquez une adresse e-mail valide pour le test.');

            return $this->redirectToRoute('admin_mail_settings');
        }

        try {
            $mailer->sendTest($to);
            $this->addFlash('success', sprintf('E-mail de test envoyé à %s : le serveur a accepté le message.', $to));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Échec de l\'envoi : '.mb_substr(strip_tags($e->getMessage()), 0, 400));
        }

        return $this->redirectToRoute('admin_mail_settings');
    }
}

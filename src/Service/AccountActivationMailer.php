<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Envoie l'email de "création de mot de passe" pour un compte portail
 * (famille, licencié) qui vient d'être créé sans mot de passe défini. Le
 * lien réutilise le circuit "mot de passe oublié" existant : s'il expire,
 * la personne peut simplement redemander un lien depuis la page de connexion.
 */
class AccountActivationMailer
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function sendActivationEmail(User $user): void
    {
        $resetToken = $this->resetPasswordHelper->generateResetToken($user);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
            ->to((string) $user->getEmail())
            ->subject('Créez votre mot de passe — ES Coutances')
            ->htmlTemplate('reset_password/activation_email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'user'       => $user,
            ])
        ;

        $this->mailer->send($email);
    }
}

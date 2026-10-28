<?php

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Service\Locale\MailLocale;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Envoie le code de vérification à deux facteurs par e-mail, avec le même expéditeur
 * et le même gabarit graphique que les autres e-mails transactionnels du site.
 */
class EmailAuthCodeMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MailLocale $mailLocale,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $this->mailLocale->runFor($user instanceof User ? $user : null, function () use ($user): void {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to($user->getEmailAuthRecipient())
                    ->subject($this->translator->trans('Votre code de vérification — ES Coutances'))
                    ->htmlTemplate('email/2fa_code.html.twig')
                    ->context([
                        'code' => $user->getEmailAuthCode(),
                    ]),
            );
        });
    }
}

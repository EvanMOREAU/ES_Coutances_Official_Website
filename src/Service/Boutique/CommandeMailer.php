<?php

namespace App\Service\Boutique;

use App\Entity\Commande;
use App\Service\Locale\MailLocale;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Emails envoyés au client : confirmation de commande, commande prête à retirer.
 * Un échec d'envoi ne doit jamais empêcher de passer ou de traiter une commande :
 * il est simplement journalisé.
 */
class CommandeMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly MailLocale $mailLocale,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function confirmation(Commande $commande): bool
    {
        return $this->send($commande, 'Votre commande %ref% — ES Coutances', 'boutique/email/confirmation.html.twig');
    }

    public function prete(Commande $commande): bool
    {
        return $this->send($commande, 'Votre commande %ref% est prête à retirer', 'boutique/email/prete.html.twig');
    }

    /** @return bool vrai si l'email est parti */
    private function send(Commande $commande, string $subject, string $template): bool
    {
        try {
            // Le client lit son e-mail dans la langue de son compte, quelle que soit celle de la personne qui déclenche l'envoi.
            $this->mailLocale->runFor($commande->getUser(), function () use ($commande, $subject, $template): void {
                $this->mailer->send(
                    (new TemplatedEmail())
                        ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                        ->to((string) $commande->getEmail())
                        ->subject($this->translator->trans($subject, ['%ref%' => (string) $commande->getReference()]))
                        ->htmlTemplate($template)
                        ->context(['commande' => $commande]),
                );
            });

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Envoi de l\'email de commande impossible : ' . $e->getMessage(), ['commande' => $commande->getReference()]);

            return false;
        }
    }
}

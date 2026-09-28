<?php

namespace App\Service\Boutique;

use App\Entity\Commande;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

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
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function confirmation(Commande $commande): bool
    {
        return $this->send($commande, sprintf('Votre commande %s — ES Coutances', $commande->getReference()), 'boutique/email/confirmation.html.twig');
    }

    public function prete(Commande $commande): bool
    {
        return $this->send($commande, sprintf('Votre commande %s est prête à retirer', $commande->getReference()), 'boutique/email/prete.html.twig');
    }

    /** @return bool vrai si l'email est parti */
    private function send(Commande $commande, string $subject, string $template): bool
    {
        try {
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to((string) $commande->getEmail())
                    ->subject($subject)
                    ->htmlTemplate($template)
                    ->context(['commande' => $commande]),
            );

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Envoi de l\'email de commande impossible : ' . $e->getMessage(), ['commande' => $commande->getReference()]);

            return false;
        }
    }
}

<?php

namespace App\Mail;

use App\Audit\AuditRecorder;
use App\Entity\MailSettings;
use App\Repository\MailSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Envoie les e-mails avec le serveur SMTP réglé dans l'administration (table mail_settings) quand il
 * est activé, et avec MAILER_DSN sinon. Chaque envoi est consigné au journal d'activité (destinataires
 * et objet, jamais le contenu).
 */
#[AsDecorator(decorates: 'mailer.mailer')]
class DynamicMailer implements MailerInterface
{
    public function __construct(
        #[AutowireDecorated] private readonly MailerInterface $inner,
        private readonly MailSettingsRepository $settings,
        private readonly SecretBox $secretBox,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AuditRecorder $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $settings = $this->settings->getSingleton();
        $custom   = $settings->isUtilisable();

        try {
            if ($custom) {
                if ($message instanceof Email && $settings->getExpediteurAdresse()) {
                    $message->from(new Address($settings->getExpediteurAdresse(), (string) $settings->getExpediteurNom()));
                }
                (new Mailer(Transport::fromDsn($this->dsn($settings), $this->dispatcher, null, $this->logger), null, $this->dispatcher))->send($message, $envelope);
            } else {
                $this->inner->send($message, $envelope);
            }
        } catch (\Throwable $e) {
            $this->audit->mail($message, false, $custom ? 'serveur réglé dans l\'administration' : 'serveur de l\'environnement', $e->getMessage());

            throw $e;
        }

        $this->audit->mail($message, true, $custom ? 'serveur réglé dans l\'administration' : 'serveur de l\'environnement');
    }

    /** DSN Symfony Mailer construit depuis les réglages (identifiants encodés). */
    public function dsn(MailSettings $settings): string
    {
        $scheme = MailSettings::CHIFFREMENT_SSL === $settings->getChiffrement() ? 'smtps' : 'smtp';
        $auth   = '';
        if (null !== $settings->getUsername()) {
            $password = $settings->getPasswordChiffre() ? ($this->secretBox->decrypt($settings->getPasswordChiffre()) ?? '') : '';
            $auth     = rawurlencode($settings->getUsername()).('' !== $password ? ':'.rawurlencode($password) : '').'@';
        }

        $options = [];
        if (MailSettings::CHIFFREMENT_AUCUN === $settings->getChiffrement()) {
            $options['auto_tls'] = 'false';
        }
        if (!$settings->isVerifierCertificat()) {
            $options['verify_peer'] = '0';
        }

        return sprintf('%s://%s%s:%d%s', $scheme, $auth, $settings->getHost(), $settings->getPort(), $options ? '?'.http_build_query($options) : '');
    }

    /**
     * Envoi de test : utilise les réglages enregistrés et renvoie l'erreur du serveur au lieu de la journaliser en silence.
     *
     * @throws TransportExceptionInterface
     */
    public function sendTest(string $to): void
    {
        $this->send(
            (new Email())
                ->to($to)
                ->from(new Address($this->settings->getSingleton()->getExpediteurAdresse() ?: 'noreply@escoutances.fr', $this->settings->getSingleton()->getExpediteurNom() ?: 'ES Coutances'))
                ->subject('Test d\'envoi — ES Coutances')
                ->text('Cet e-mail confirme que le serveur d\'envoi de l\'application fonctionne.'),
        );
    }
}

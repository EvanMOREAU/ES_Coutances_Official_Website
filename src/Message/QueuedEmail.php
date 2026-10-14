<?php

namespace App\Message;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;

/** E-mail déjà rendu, en attente d'envoi par le worker Messenger (voir DynamicMailer). */
final readonly class QueuedEmail
{
    public function __construct(
        public RawMessage $message,
        public ?Envelope $envelope = null,
    ) {
    }
}

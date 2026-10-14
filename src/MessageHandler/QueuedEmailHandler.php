<?php

namespace App\MessageHandler;

use App\Mail\DynamicMailer;
use App\Message\QueuedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Envoie un e-mail mis en file : mêmes réglages SMTP et même journal d'activité qu'un envoi direct. */
#[AsMessageHandler]
final readonly class QueuedEmailHandler
{
    public function __construct(private DynamicMailer $mailer)
    {
    }

    public function __invoke(QueuedEmail $queued): void
    {
        $this->mailer->deliver($queued->message, $queued->envelope);
    }
}

<?php

namespace App\Tests\Unit\Mail;

use App\Audit\AuditRecorder;
use App\Entity\MailSettings;
use App\Mail\DynamicMailer;
use App\Mail\SecretBox;
use App\Message\QueuedEmail;
use App\MessageHandler\QueuedEmailHandler;
use App\Repository\MailSettingsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Email;

/** MAILER_ASYNC=1 : l'e-mail est rendu puis mis en file ; le worker l'envoie ensuite avec le même mailer. */
final class DynamicMailerQueueTest extends TestCase
{
    private MailerInterface&MockObject $inner;
    private MessageBusInterface&MockObject $bus;
    private BodyRendererInterface&MockObject $renderer;

    private function mailer(bool $async): DynamicMailer
    {
        $settings = $this->createStub(MailSettingsRepository::class);
        $settings->method('getSingleton')->willReturn(new MailSettings());

        $this->inner    = $this->createMock(MailerInterface::class);
        $this->bus      = $this->createMock(MessageBusInterface::class);
        $this->renderer = $this->createMock(BodyRendererInterface::class);

        return new DynamicMailer(
            $this->inner,
            $settings,
            new SecretBox('secret'),
            new EventDispatcher(),
            $this->createStub(AuditRecorder::class),
            new NullLogger(),
            $this->bus,
            $this->renderer,
            $async,
        );
    }

    public function testSynchronousModeSendsDirectly(): void
    {
        $mailer = $this->mailer(false);
        $email  = (new Email())->from('a@test.local')->to('b@test.local')->subject('Bonjour')->text('Salut');

        $this->inner->expects(self::once())->method('send')->with($email);
        $this->bus->expects(self::never())->method('dispatch');
        $this->renderer->expects(self::never())->method('render');

        $mailer->send($email);
    }

    public function testAsynchronousModeRendersThenQueuesWithoutTheContext(): void
    {
        $mailer = $this->mailer(true);
        $email  = (new TemplatedEmail())->from('a@test.local')->to('b@test.local')->subject('Bonjour')->htmlTemplate('email/notification.html.twig')->context(['recipient' => new \stdClass()]);

        $this->renderer->expects(self::once())->method('render')->with($email);
        $this->inner->expects(self::never())->method('send');
        $this->bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (QueuedEmail $queued): bool => $queued->message === $email && [] === $email->getContext()))
            ->willReturn(new Envelope(new \stdClass()));

        $mailer->send($email);
    }

    public function testWorkerHandlerDeliversTheQueuedEmail(): void
    {
        $mailer = $this->mailer(true);
        $email  = (new Email())->from('a@test.local')->to('b@test.local')->subject('Bonjour')->text('Salut');

        $this->inner->expects(self::once())->method('send')->with($email);
        $this->bus->expects(self::never())->method('dispatch');
        $this->renderer->expects(self::never())->method('render');

        (new QueuedEmailHandler($mailer))(new QueuedEmail($email));
    }
}

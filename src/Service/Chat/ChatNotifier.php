<?php

namespace App\Service\Chat;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Service\Notification\NotificationPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mime\Address;

/**
 * Prévient par e-mail les personnes qui reçoivent un message alors qu'elles ne sont pas en ligne.
 * Au plus un e-mail par discussion et par tranche de 15 minutes, pour ne pas inonder la boîte de réception.
 * Un échec d'envoi ne bloque jamais la messagerie : il est simplement journalisé.
 */
class ChatNotifier implements EventSubscriberInterface
{
    /** @var list<Message> messages à signaler une fois la réponse HTTP envoyée */
    private array $pending = [];

    private const THROTTLE = 'PT15M';
    private const ONLINE_WINDOW = 'PT50S';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UserRepository $users,
        private readonly PermissionChecker $permissions,
        private readonly NotificationPreferences $prefs,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'flush'];
    }

    /** Les e-mails partent après la réponse au navigateur : l'envoi d'un message n'attend jamais le serveur SMTP. */
    public function notify(Message $message): void
    {
        $this->pending[] = $message;
    }

    public function flush(): void
    {
        $messages      = $this->pending;
        $this->pending = [];
        foreach ($messages as $message) {
            try {
                $this->doNotify($message);
            } catch (\Throwable $e) {
                $this->logger->error('Notification e-mail de la messagerie impossible : '.$e->getMessage());
            }
        }
    }

    private function doNotify(Message $message): void
    {
        $conversation = $message->getConversation();
        $author       = $message->getAuthor();
        if ($author === null) {
            return;
        }
        $limit = (new \DateTimeImmutable())->sub(new \DateInterval(self::THROTTLE));

        if ($conversation->isSupport() && $author->getId() === $conversation->getCustomer()?->getId()) {
            // Un client écrit au support : toute l'équipe habilitée est prévenue (une fois par tranche).
            $last = $conversation->getStaffNotifiedAt();
            if ($last !== null && $last > $limit) {
                return;
            }
            foreach ($this->users->findAll() as $user) {
                if ($user->isStaff() && $user->getId() !== $author->getId() && $this->permissions->can('messagerie.support', $user) && !$this->isOnline($user) && $this->prefs->wants($user, 'messages', NotificationPreferences::EMAIL)) {
                    $this->send($user, $message, 'Un client a écrit au support');
                }
            }
            $conversation->markStaffNotified();
            $this->em->flush();

            return;
        }

        foreach ($conversation->getParticipants() as $participant) {
            $user = $participant->getUser();
            if ($user->getId() === $author->getId() || $this->isOnline($user) || !$this->prefs->wants($user, 'messages', NotificationPreferences::EMAIL)) {
                continue;
            }
            $last = $participant->getLastNotifiedAt();
            if ($last !== null && $last > $limit) {
                continue;
            }
            // Dans une discussion de support, un membre de l'équipe n'est prévenu que par la règle ci-dessus.
            if ($conversation->isSupport() && $user->isStaff()) {
                continue;
            }
            $this->send($user, $message, $conversation->isSupport() ? 'Le support vous a répondu' : 'Vous avez reçu un nouveau message');
            $participant->markNotified();
            $this->em->flush();
        }
    }

    private function isOnline(User $user): bool
    {
        $seen = $user->getLastSeenAt();

        return $seen !== null && $seen >= (new \DateTimeImmutable())->sub(new \DateInterval(self::ONLINE_WINDOW));
    }

    private function send(User $recipient, Message $message, string $subject): void
    {
        $conversation = $message->getConversation();
        $fromSupport  = $conversation->isSupport() && !$recipient->isStaff();
        $sender       = $fromSupport ? 'Le support' : ($message->getAuthor()?->getNomComplet() ?: 'Quelqu\'un');

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to((string) $recipient->getEmail())
                ->subject($subject.' — ES Coutances')
                ->htmlTemplate('email/chat_notification.html.twig')
                ->context([
                    'recipient'  => $recipient,
                    'sender'     => $sender,
                    'staff'      => $recipient->isStaff(),
                    'support'    => $conversation->isSupport(),
                    'conversationId' => $conversation->getId(),
                    'withDocument'   => $message->getBody() === '' ,
                ]),
        );
    }
}

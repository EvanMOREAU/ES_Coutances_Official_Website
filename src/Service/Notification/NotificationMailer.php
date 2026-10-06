<?php

namespace App\Service\Notification;

use App\Entity\Commande;
use App\Entity\Entrainement;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Service\PlanningService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * E-mails « il y a du nouveau » (nouvelle commande, nouvelle famille, nouveau licencié, nouveau compte,
 * nouvel événement du planning), envoyés aux comptes qui ne les ont pas désactivés.
 *
 * Les créations sont repérées au fil de la requête puis traitées à la fin (après la réponse), et un
 * import ou une série en masse (plus de 3 éléments du même type) donne un seul e-mail récapitulatif
 * — sauf le planning, que l'on n'envoie pas en masse. Un échec d'envoi n'affecte jamais l'action de l'utilisateur.
 */
#[AsDoctrineListener(event: Events::postPersist)]
class NotificationMailer
{
    private const BULK_LIMIT = 3;

    /** @var array<string, list<object>> */
    private array $pending = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UserRepository $users,
        private readonly PermissionChecker $permissions,
        private readonly NotificationPreferences $prefs,
        private readonly PlanningService $planning,
        private readonly UrlGeneratorInterface $urls,
        private readonly Security $security,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $type   = match (true) {
            $entity instanceof Commande     => 'commandes',
            $entity instanceof Famille      => 'familles',
            $entity instanceof Licencie     => 'licencies',
            $entity instanceof Entrainement => 'planning',
            $entity instanceof User         => $entity->isStaff() ? null : 'utilisateurs',
            default                         => null,
        };
        if ($type !== null) {
            $this->pending[$type][] = $entity;
        }
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function flush(): void
    {
        $pending       = $this->pending;
        $this->pending = [];
        if ($pending === []) {
            return;
        }
        $actor   = $this->security->getUser();
        $actorId = $actor instanceof User ? $actor->getId() : null;

        foreach ($pending as $type => $entities) {
            try {
                $type === 'planning' ? $this->planningMails($entities, $actorId) : $this->staffMails($type, $entities, $actorId);
            } catch (\Throwable $e) {
                $this->logger->error('E-mail de notification impossible : '.$e->getMessage());
            }
        }
    }

    /** @param list<object> $entities */
    private function staffMails(string $type, array $entities, ?int $actorId): void
    {
        $permission = NotificationPreferences::CATALOG[$type]['permission'];
        $count      = count($entities);
        $labels     = ['commandes' => ['commande', 'commandes'], 'familles' => ['famille', 'familles'], 'licencies' => ['licencié', 'licenciés'], 'utilisateurs' => ['compte', 'comptes']];
        $routes     = ['commandes' => 'admin_commande_index', 'familles' => 'admin_famille_index', 'licencies' => 'admin_licencie_index', 'utilisateurs' => 'admin_user_index'];

        if ($count > self::BULK_LIMIT) {
            $title = sprintf('%d nouveaux %s', $count, $labels[$type][1]);
            $lines = ["Plusieurs éléments viennent d'être ajoutés en une seule fois (import, création en série…)."];
        } else {
            $title = sprintf('Nouveau%s %s', $count > 1 ? 'x' : '', $labels[$type][$count > 1 ? 1 : 0]);
            $lines = array_map(fn (object $e) => $this->describe($e), $entities);
        }
        $url = $this->urls->generate($routes[$type], [], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($this->users->findAll() as $user) {
            if (!$user->isStaff() || $user->getId() === $actorId || !$this->permissions->can((string) $permission, $user) || !$this->prefs->wants($user, $type, NotificationPreferences::EMAIL)) {
                continue;
            }
            $this->send($user, $title, $lines, $url, "Ouvrir dans l'administration");
        }
    }

    /** @param list<Entrainement> $events */
    private function planningMails(array $events, ?int $actorId): void
    {
        if (count($events) > self::BULK_LIMIT) {
            return; // création en série : pas d'e-mail en masse
        }
        foreach ($events as $event) {
            $title = sprintf('%s : %s', $event->isEvenement() ? 'Nouvel événement' : ($event->isRencontre() ? 'Nouvelle rencontre' : 'Nouvel entraînement'), $event->getTitre());
            $when  = ($event->getDate()?->format('d/m/Y') ?? '').' à '.($event->getHeureDebut()?->format('H:i') ?? '');
            $lines = array_values(array_filter([$when, $event->getLieu() ? 'Lieu : '.$event->getLieu() : null]));

            foreach ($this->users->findAll() as $user) {
                if ($user->getId() === $actorId || !$this->prefs->wants($user, 'planning', NotificationPreferences::EMAIL)) {
                    continue;
                }
                if ($user->isStaff()) {
                    if (!$this->permissions->can('planning.voir', $user) || !$this->planning->visibleEvenement($event, $user)) {
                        continue;
                    }
                    $url = $this->urls->generate('admin_planning_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
                } else {
                    if ($event->isEvenement() || !$this->planning->concerns($event, $this->planning->licenciesFor($user))) {
                        continue;
                    }
                    $url = $this->urls->generate('portail_planning', [], UrlGeneratorInterface::ABSOLUTE_URL);
                }
                $this->send($user, $title, $lines, $url, 'Voir le planning');
            }
        }
    }

    private function describe(object $entity): string
    {
        return match (true) {
            $entity instanceof Commande => sprintf('Commande %s de %s', $entity->getReference(), $entity->getNomComplet()),
            $entity instanceof Famille  => sprintf('Famille %s', $entity->getNom()),
            $entity instanceof Licencie => sprintf('%s %s', $entity->getPrenom(), $entity->getNom()),
            $entity instanceof User     => sprintf('%s (%s)', $entity->getNomComplet() ?: $entity->getEmail(), $entity->getEmail()),
            default                     => '',
        };
    }

    /** @param list<string> $lines */
    private function send(User $recipient, string $title, array $lines, string $url, string $linkLabel): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to((string) $recipient->getEmail())
                ->subject($title.' — ES Coutances')
                ->htmlTemplate('email/notification.html.twig')
                ->context(['recipient' => $recipient, 'title' => $title, 'lines' => $lines, 'url' => $url, 'linkLabel' => $linkLabel]),
        );
    }
}

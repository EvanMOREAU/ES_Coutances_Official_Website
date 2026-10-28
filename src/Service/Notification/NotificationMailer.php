<?php

namespace App\Service\Notification;

use App\Entity\Adhesion;
use App\Entity\ArticleVariante;
use App\Entity\Commande;
use App\Entity\ContratPartenaire;
use App\Entity\Entrainement;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Partenaire;
use App\Entity\Reglement;
use App\Entity\User;
use App\Repository\BoutiqueSettingsRepository;
use App\Repository\UserRepository;
use App\Security\PermissionChecker;
use App\Service\Locale\Locales;
use App\Service\Money;
use App\Service\PlanningService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * E-mails « il y a du nouveau », envoyés aux comptes qui ne les ont pas désactivés (voir NotificationPreferences) :
 *
 *  - équipe du club : nouvelle commande, famille, licencié, adhésion (licence), compte, partenaire / contrat,
 *    stock bas, message du formulaire de contact ;
 *  - familles et licenciés : nouvel événement du planning, licencié ajouté à la famille, licence enregistrée,
 *    règlement reçu (et, par la commande `app:rappels:envoyer`, rappel la veille d'un événement).
 *
 * Les événements sont repérés au fil de la requête puis traités à la fin (après la réponse), et un import ou une
 * série en masse (plus de 3 éléments du même type) donne un seul e-mail récapitulatif à l'équipe — et aucun
 * e-mail aux familles, qu'on ne veut pas inonder lors d'un import. Chaque e-mail est rédigé dans la langue de son
 * destinataire. Un échec d'envoi n'affecte jamais l'action de l'utilisateur : il est journalisé.
 *
 * Contrairement à l'ancienne version, l'auteur de l'action est lui aussi prévenu s'il a laissé la rubrique
 * activée : c'est à lui de la décocher (sans quoi un administrateur seul ne recevrait jamais rien).
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class NotificationMailer
{
    private const BULK_LIMIT = 3;

    /** Rubrique d'équipe => route de la liste ouverte par le bouton de l'e-mail. */
    private const STAFF_ROUTES = [
        'commandes'    => 'admin_commande_index',
        'familles'     => 'admin_famille_index',
        'licencies'    => 'admin_licencie_index',
        'utilisateurs' => 'admin_user_index',
        'adhesions'    => 'admin_adhesion_index',
        'partenaires'  => 'admin_partenaire_index',
        'stock'        => 'admin_article_index',
    ];

    /** @var array<string, list<mixed>> */
    private array $pending = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly UserRepository $users,
        private readonly PermissionChecker $permissions,
        private readonly NotificationPreferences $prefs,
        private readonly PlanningService $planning,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly BoutiqueSettingsRepository $boutiqueSettings,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        match (true) {
            $entity instanceof Commande          => $this->pending['commandes'][] = $entity,
            $entity instanceof Famille           => $this->pending['familles'][] = $entity,
            $entity instanceof Licencie          => $this->recordLicencie($entity),
            $entity instanceof Adhesion          => $this->recordAdhesion($entity),
            $entity instanceof Partenaire,
            $entity instanceof ContratPartenaire => $this->pending['partenaires'][] = $entity,
            $entity instanceof Entrainement      => $this->pending['planning'][] = $entity,
            $entity instanceof User              => $entity->isStaff() ? null : $this->pending['utilisateurs'][] = $entity,
            default                              => null,
        };
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Reglement && $args->hasChangedField('recu') && true === $args->getNewValue('recu')) {
            $this->pending['reglements'][] = $entity;
        }
        if ($entity instanceof ArticleVariante && $args->hasChangedField('stock')) {
            $threshold = $this->boutiqueSettings->getSingleton()?->getSeuilStockBas() ?? 3;
            $old       = (int) $args->getOldValue('stock');
            $new       = (int) $args->getNewValue('stock');
            if ($threshold > 0 && $new < $old && (($new <= $threshold && $old > $threshold) || (0 === $new))) {
                $this->pending['stock'][] = $entity;
            }
        }
    }

    /** Message du formulaire de contact du site : prévient l'équipe qui a laissé la rubrique activée. */
    public function contactReceived(string $name, string $email, string $subject, string $message): void
    {
        $this->pending['contact'][] = compact('name', 'email', 'subject', 'message');
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function flush(): void
    {
        $pending       = $this->pending;
        $this->pending = [];
        if ([] === $pending) {
            return;
        }

        foreach ($pending as $type => $entities) {
            try {
                match ($type) {
                    'planning'     => $this->planningMails($entities),
                    'contact'      => $this->contactMails($entities),
                    'mes_licencies' => $this->familyMails('mes_licencies', $entities, $pending),
                    'paiements'    => $this->familyMails('paiements', $entities, $pending),
                    'reglements'   => $this->familyMails('reglements', $entities, $pending),
                    default        => $this->staffMails($type, $entities),
                };
            } catch (\Throwable $e) {
                $this->logger->error('E-mail de notification impossible : '.$e->getMessage());
            }
        }
    }

    private function recordLicencie(Licencie $licencie): void
    {
        $this->pending['licencies'][]     = $licencie;
        $this->pending['mes_licencies'][] = $licencie;
    }

    private function recordAdhesion(Adhesion $adhesion): void
    {
        $this->pending['adhesions'][]  = $adhesion;
        $this->pending['paiements'][]  = $adhesion;
    }

    // ---------------------------------------------------------------- équipe

    /** @param list<mixed> $entities */
    private function staffMails(string $type, array $entities): void
    {
        if (!isset(self::STAFF_ROUTES[$type])) {
            return;
        }
        $permission = (string) NotificationPreferences::CATALOG[$type]['permission'];
        $url        = $this->urls->generate(self::STAFF_ROUTES[$type], [], UrlGeneratorInterface::ABSOLUTE_URL);
        $count      = \count($entities);
        $content    = [];

        foreach ($this->users->findStaff() as $user) {
            if (!$this->canMail($user) || !$this->permissions->can($permission, $user) || !$this->prefs->wants($user, $type, NotificationPreferences::EMAIL)) {
                continue;
            }
            $locale = Locales::normalize($user->getLocale());
            if (!isset($content[$locale])) {
                $content[$locale] = $count > self::BULK_LIMIT
                    ? [$this->translator->trans('notif.mail.bulk.'.$type, ['%count%' => $count], null, $locale), [$this->translator->trans('notif.mail.bulk_line', [], null, $locale)]]
                    : [$this->translator->trans('notif.mail.title.'.$type, ['%count%' => $count], null, $locale), array_map(fn (mixed $e): string => $this->describe($e, $locale), $entities)];
            }
            [$title, $lines] = $content[$locale];
            $this->send($user, $title, $lines, $url, $this->translator->trans('notif.mail.open_admin', [], null, $locale));
        }
    }

    /** @param list<mixed> $entities */
    private function contactMails(array $entities): void
    {
        $url = $this->urls->generate('admin_reglages_contact', [], UrlGeneratorInterface::ABSOLUTE_URL);
        foreach ($entities as $contact) {
            foreach ($this->users->findStaff() as $user) {
                if (!$this->canMail($user) || !$this->permissions->can('reglages.voir', $user) || !$this->prefs->wants($user, 'contact', NotificationPreferences::EMAIL)) {
                    continue;
                }
                $locale = Locales::normalize($user->getLocale());
                $this->send(
                    $user,
                    $this->translator->trans('notif.mail.title.contact', [], null, $locale),
                    [
                        $this->translator->trans('notif.mail.line.contact_from', ['%name%' => $contact['name'], '%email%' => $contact['email']], null, $locale),
                        $this->translator->trans('notif.mail.line.contact_subject', ['%subject%' => $contact['subject']], null, $locale),
                        mb_strimwidth((string) $contact['message'], 0, 300, '…'),
                    ],
                    $url,
                    $this->translator->trans('notif.mail.open_admin', [], null, $locale),
                );
            }
        }
    }

    // ------------------------------------------------------ familles et licenciés

    /**
     * @param list<mixed>                $entities
     * @param array<string, list<mixed>> $all
     */
    private function familyMails(string $type, array $entities, array $all): void
    {
        if (\count($entities) > self::BULK_LIMIT) {
            return; // import ou saisie en série : on n'écrit pas aux familles
        }
        $newFamilies = $all['familles'] ?? [];

        foreach ($entities as $entity) {
            if ('mes_licencies' === $type && $entity instanceof Licencie) {
                $famille = $entity->getFamille();
                if (null === $famille || \in_array($famille, $newFamilies, true)) {
                    continue; // la famille vient d'être créée avec ce licencié : rien à annoncer
                }
                $recipient = $famille->getUser();
                if (null === $recipient || $recipient === $entity->getUser()) {
                    continue;
                }
                $this->familyMail($recipient, 'mes_licencies', 'notif.mail.title.mes_licencies', ['%name%' => trim($entity->getPrenom().' '.$entity->getNom())], [
                    ['notif.mail.line.mes_licencies', ['%name%' => trim($entity->getPrenom().' '.$entity->getNom()), '%season%' => (string) $entity->getSaison()?->getLibelle()]],
                ], 'portail_index', 'notif.mail.open_account');
            } elseif ('paiements' === $type && $entity instanceof Adhesion) {
                $licencie  = $entity->getLicencie();
                $recipient = $licencie?->getFamille()?->getUser();
                if (null === $licencie || null === $recipient) {
                    continue;
                }
                $name = trim($licencie->getPrenom().' '.$licencie->getNom());
                $this->familyMail($recipient, 'paiements', 'notif.mail.title.adhesion', ['%name%' => $name], [
                    ['notif.mail.line.adhesion', ['%name%' => $name, '%season%' => (string) $entity->getSaison()?->getLibelle(), '%amount%' => Money::format($entity->getMontantDuCentimes())]],
                ], 'portail_factures', 'notif.mail.open_invoices');
            } elseif ('reglements' === $type && $entity instanceof Reglement) {
                $licencie  = $entity->getAdhesion()?->getLicencie();
                $recipient = $licencie?->getFamille()?->getUser();
                if (null === $licencie || null === $recipient) {
                    continue;
                }
                $name = trim($licencie->getPrenom().' '.$licencie->getNom());
                $this->familyMail($recipient, 'paiements', 'notif.mail.title.reglement', ['%amount%' => Money::format($entity->getMontantCentimes())], [
                    ['notif.mail.line.reglement', ['%name%' => $name, '%amount%' => Money::format($entity->getMontantCentimes())]],
                ], 'portail_factures', 'notif.mail.open_invoices');
            }
        }
    }

    /**
     * @param array<string, scalar>                               $titleParams
     * @param list<array{0: string, 1: array<string, scalar>}>    $lines
     */
    private function familyMail(User $recipient, string $pref, string $titleKey, array $titleParams, array $lines, string $route, string $linkKey): void
    {
        if (!$this->canMail($recipient) || !$this->prefs->wants($recipient, $pref, NotificationPreferences::EMAIL)) {
            return;
        }
        $locale = Locales::normalize($recipient->getLocale());
        $this->send(
            $recipient,
            $this->translator->trans($titleKey, $titleParams, null, $locale),
            array_map(fn (array $l): string => $this->translator->trans($l[0], $l[1], null, $locale), $lines),
            $this->urls->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->translator->trans($linkKey, [], null, $locale),
        );
    }

    // --------------------------------------------------------------- planning

    /** @param list<mixed> $entities */
    private function planningMails(array $entities): void
    {
        $events = array_values(array_filter($entities, static fn (mixed $e): bool => $e instanceof Entrainement));
        if (\count($events) > self::BULK_LIMIT) {
            return; // création en série : pas d'e-mail en masse
        }
        $users = $this->users->findAll(); // chargés une seule fois pour tous les événements
        foreach ($events as $event) {
            $kind = $event->isEvenement() ? 'event' : ($event->isRencontre() ? 'match' : 'training');
            $this->notifyEvent($event, $users, 'planning', 'notif.mail.title.planning_'.$kind);
        }
    }

    /**
     * Prévient par e-mail les comptes concernés par un événement du planning (équipe : selon ses autorisations et le partage
     * de l'événement ; familles et licenciés : selon leurs catégories). Sert aux nouveaux événements et aux rappels de la veille.
     *
     * @param list<User> $users
     *
     * @return int nombre d'e-mails envoyés
     */
    public function notifyEvent(Entrainement $event, array $users, string $pref, string $titleKey): int
    {
        $sent = 0;
        foreach ($users as $user) {
            if (!$this->canMail($user) || !$this->prefs->wants($user, $pref, NotificationPreferences::EMAIL)) {
                continue;
            }
            if ($user->isStaff()) {
                if (!$this->permissions->can('planning.voir', $user) || !$this->planning->visibleEvenement($event, $user)) {
                    continue;
                }
                $route = 'admin_planning_index';
            } else {
                if ($event->isEvenement() || !$this->planning->concerns($event, $this->planning->licenciesFor($user))) {
                    continue;
                }
                $route = 'portail_planning';
            }
            $locale = Locales::normalize($user->getLocale());
            $this->send(
                $user,
                $this->translator->trans($titleKey, ['%title%' => $event->getTitre()], null, $locale),
                $this->eventLines($event, $locale),
                $this->urls->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->translator->trans('notif.mail.open_planning', [], null, $locale),
            );
            ++$sent;
        }

        return $sent;
    }

    /** @return list<string> date, heure et lieu d'un événement, dans la langue donnée */
    public function eventLines(Entrainement $event, string $locale): array
    {
        $lines = [$this->translator->trans('notif.mail.line.when', [
            '%date%' => $event->getDate()?->format('d/m/Y') ?? '',
            '%time%' => $event->getHeureDebut()?->format('H:i') ?? '',
        ], null, $locale)];
        if ($event->getLieu()) {
            $lines[] = $this->translator->trans('notif.mail.line.where', ['%place%' => $event->getLieu()], null, $locale);
        }

        return $lines;
    }

    // ------------------------------------------------------------------ outils

    private function describe(mixed $entity, string $locale): string
    {
        $t = fn (string $key, array $params): string => $this->translator->trans($key, $params, null, $locale);

        return match (true) {
            $entity instanceof Commande          => $t('notif.mail.line.commande', ['%ref%' => (string) $entity->getReference(), '%name%' => $entity->getNomComplet(), '%total%' => Money::format($entity->getTotalCentimes())]),
            $entity instanceof Famille           => $t('notif.mail.line.famille', ['%name%' => $entity->getNomAffiche()]),
            $entity instanceof Licencie          => $t('notif.mail.line.licencie', ['%name%' => trim($entity->getPrenom().' '.$entity->getNom()), '%season%' => (string) $entity->getSaison()?->getLibelle()]),
            $entity instanceof Adhesion          => $t('notif.mail.line.adhesion_staff', ['%name%' => (string) $entity->getLicencieAffichage(), '%season%' => (string) $entity->getSaison()?->getLibelle(), '%amount%' => Money::format($entity->getMontantDuCentimes())]),
            $entity instanceof User              => $t('notif.mail.line.user', ['%name%' => $entity->getNomComplet() ?: (string) $entity->getEmail(), '%email%' => (string) $entity->getEmail()]),
            $entity instanceof Partenaire        => $t('notif.mail.line.partenaire', ['%name%' => (string) $entity->getNom()]),
            $entity instanceof ContratPartenaire => $t('notif.mail.line.contrat', ['%title%' => (string) $entity->getTitre(), '%partner%' => (string) $entity->getPartenaire()?->getNom()]),
            $entity instanceof ArticleVariante   => $t('notif.mail.line.stock', ['%article%' => (string) $entity->getArticle()?->getNom(), '%size%' => (string) $entity->getLibelle(), '%stock%' => $entity->getStock()]),
            default                              => '',
        };
    }

    /** Un compte supprimé/anonymisé ou doté d'une adresse provisoire d'import ne reçoit rien. */
    private function canMail(User $user): bool
    {
        $email = (string) $user->getEmail();

        return !$user->isAnonymized() && false !== filter_var($email, \FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($email), '@import.local');
    }

    /** @param list<string> $lines */
    public function send(User $recipient, string $title, array $lines, string $url, string $linkLabel): void
    {
        $locale = Locales::normalize($recipient->getLocale());
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to((string) $recipient->getEmail())
                ->subject($title.' — ES Coutances')
                ->htmlTemplate('email/notification.html.twig')
                ->context(['recipient' => $recipient, 'title' => $title, 'lines' => $lines, 'url' => $url, 'linkLabel' => $linkLabel, 'locale' => $locale]),
        );
    }
}

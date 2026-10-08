<?php

namespace App\Service\Chat;

use App\Entity\ChatAttachment;
use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ChatAttachmentRepository;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Security\PermissionChecker;
use App\Service\FileManager\FileManagerException;
use App\Service\FileManager\FileStorage;
use App\Service\FileManager\UserDocumentSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Messagerie du club : les joueurs / familles écrivent aux administrateurs, les
 * membres de l'équipe écrivent à qui ils veulent, en direct ou en groupe.
 *
 * Règles d'accès : un compte « famille / licencié » ne peut ouvrir une
 * discussion qu'avec un administrateur, jamais avec un autre licencié ou une
 * autre famille ; un compte de l'équipe peut écrire à tout le monde. Un membre
 * de l'équipe qui n'a pas l'autorisation « messagerie.utiliser » (profil
 * d'autorisation) n'apparaît dans la liste de personne, et ne peut être ajouté à
 * aucune discussion. Une discussion n'est visible que de ses participants.
 */
class ChatService
{
    /** Au-delà, un utilisateur est considéré comme hors ligne (le navigateur interroge le serveur toutes les quelques secondes). */
    private const ONLINE_WINDOW = 'PT50S';

    /** On n'écrit la « dernière activité » en base qu'une fois par intervalle, pas à chaque interrogation. */
    private const PRESENCE_THROTTLE = 'PT20S';

    private const MAX_GROUP_SIZE = 25;

    /** Un compte « famille / licencié » n'envoie que des documents courants, de taille raisonnable. */
    private const MEMBER_MAX_BYTES = 10 * 1024 * 1024;
    private const MEMBER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp', 'txt'];

    /** @var array<int, ChatAttachment|null> documents joints déjà chargés, par identifiant de message */
    private array $attachments = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages,
        private readonly PermissionChecker $permissions,
        private readonly ChatAttachmentRepository $attachmentRepository,
        private readonly FileStorage $storage,
        private readonly UserDocumentSpace $space,
        private readonly ChatNotifier $notifier,
    ) {
    }

    // -- Clients et support ------------------------------------------------------

    /** @var array<int, true>|null identifiants des comptes rattachés à une famille ou à un licencié */
    private ?array $memberIds = null;

    /** Un client : compte boutique, ni équipe du club, ni famille, ni licencié. */
    public function isClient(User $user): bool
    {
        if ($user->isStaff()) {
            return false;
        }
        if ($this->memberIds === null) {
            $this->memberIds = [];
            foreach ([Famille::class, Licencie::class] as $class) {
                foreach ($this->em->createQuery(sprintf('SELECT IDENTITY(e.user) FROM %s e WHERE e.user IS NOT NULL', $class))->getSingleColumnResult() as $id) {
                    $this->memberIds[(int) $id] = true;
                }
            }
        }

        return !isset($this->memberIds[(int) $user->getId()]);
    }

    /** Une personne du club habilitée à répondre aux clients (autorisation « messagerie.support »). */
    public function canSupport(User $user): bool
    {
        return $user->isStaff() && $this->permissions->can('messagerie.support', $user);
    }

    /** Participant, ou membre habilité du support devant une discussion de support. */
    public function canAccess(Conversation $conversation, User $user): bool
    {
        return $conversation->participantFor($user) !== null || ($conversation->isSupport() && $this->canSupport($user));
    }

    /**
     * Rattachement d'un utilisateur à la discussion. Un membre du support y est inscrit à sa première
     * ouverture : c'est ce qui permet de suivre ce qu'il a lu.
     */
    private function participation(Conversation $conversation, User $user): ?ConversationParticipant
    {
        $participant = $conversation->participantFor($user);
        if ($participant === null && $conversation->isSupport() && $this->canSupport($user)) {
            $participant = $conversation->addParticipant($user);
        }

        return $participant;
    }

    /** La discussion de support d'un client (créée à la première demande). */
    public function supportConversationFor(User $client): Conversation
    {
        $conversation = $this->conversations->findSupportOf($client);
        if ($conversation === null) {
            $conversation = (new Conversation())->markAsSupport($client);
            $conversation->addParticipant($client);
            $this->em->persist($conversation);
            $this->em->flush();
        }

        return $conversation;
    }

    /** @return list<Conversation> */
    private function conversationsFor(User $me): array
    {
        if ($this->isClient($me)) {
            // Un client n'a que sa discussion de support : d'éventuelles anciennes discussions ne comptent pas.
            $support = $this->conversations->findSupportOf($me);

            return $support !== null ? [$support] : [];
        }
        $conversations = $this->conversations->findForUser($me);
        if ($this->canSupport($me)) {
            $byId = [];
            foreach (array_merge($conversations, $this->conversations->findSupport()) as $conversation) {
                $byId[$conversation->getId()] = $conversation;
            }
            $conversations = array_values($byId);
            usort($conversations, static fn (Conversation $a, Conversation $b) => [$b->getUpdatedAt(), $b->getId()] <=> [$a->getUpdatedAt(), $a->getId()]);
        }

        return $conversations;
    }

    /**
     * Non-lus par discussion, y compris les discussions de support que ce membre de l'équipe n'a pas encore ouvertes.
     *
     * @param list<Conversation> $conversations
     *
     * @return array<int, int>
     */
    private function unreadMap(User $me, array $conversations): array
    {
        $counts = $this->conversations->unreadCounts($me);
        if ($this->isClient($me)) {
            $ids    = array_map(static fn (Conversation $c) => (int) $c->getId(), $conversations);
            $counts = array_intersect_key($counts, array_flip($ids));
        }
        if ($this->canSupport($me)) {
            $unopened = [];
            foreach ($conversations as $conversation) {
                if ($conversation->isSupport() && $conversation->participantFor($me) === null) {
                    $unopened[] = (int) $conversation->getId();
                }
            }
            foreach ($this->conversations->supportUnreadForStaff($unopened) as $id => $unread) {
                $counts[$id] = $unread;
            }
        }

        return $counts;
    }

    // -- Présence --------------------------------------------------------------

    public function touchPresence(User $user): void
    {
        $now  = new \DateTimeImmutable();
        $last = $user->getLastSeenAt();
        if ($last === null || $last < $now->sub(new \DateInterval(self::PRESENCE_THROTTLE))) {
            $user->setLastSeenAt($now);
            $this->em->flush();
        }
    }

    public function isOnline(User $user): bool
    {
        $seen = $user->getLastSeenAt();

        return $seen !== null && $seen >= (new \DateTimeImmutable())->sub(new \DateInterval(self::ONLINE_WINDOW));
    }

    // -- Lecture ---------------------------------------------------------------

    /**
     * Liste des discussions de l'utilisateur, prête à être affichée.
     *
     * @return list<array<string, mixed>>
     */
    public function summaries(User $me): array
    {
        $conversations = $this->conversationsFor($me);
        $ids           = array_map(static fn (Conversation $c) => (int) $c->getId(), $conversations);
        $last          = $this->messages->lastOf($ids);
        $this->preloadAttachments(array_values($last));
        $unread        = $this->unreadMap($me, $conversations);

        $summaries = [];
        foreach ($conversations as $conversation) {
            $others  = $this->others($conversation, $me);
            $message = $last[$conversation->getId()] ?? null;
            $peer    = $this->peerOf($conversation, $me, $others);
            $asClient = $conversation->isSupport() && !$me->isStaff(); // le client ne voit qu'un « Support »

            $summaries[] = [
                'id'          => $conversation->getId(),
                'title'       => $this->title($conversation, $me),
                'initials'    => $conversation->isGroup() ? null : ($asClient ? 'S' : $this->initials($peer)),
                'group'       => $conversation->isGroup(),
                'support'     => $conversation->isSupport(),
                'client'      => $me->isStaff() && ($conversation->isSupport() || ($peer !== null && $this->isClient($peer))),
                'members'     => count($others) + 1,
                'people'      => $conversation->isGroup() ? $this->people($conversation, $me) : [],
                'online'      => $peer !== null && $this->isOnline($peer),
                'lastSeen'    => $peer?->getLastSeenAt()?->format(\DATE_ATOM),
                'avatar'      => $peer?->getAvatarName() ? '/uploads/avatars/'.$peer->getAvatarName() : null,
                'preview'     => $message ? $this->preview($message, $me, $conversation->isGroup() || ($conversation->isSupport() && $me->isStaff())) : 'Nouvelle discussion',
                'previewAt'   => ($message?->getCreatedAt() ?? $conversation->getCreatedAt())->format(\DATE_ATOM),
                'unread'      => $unread[$conversation->getId()] ?? 0,
                'lastId'      => $message?->getId() ?? 0,
            ];
        }

        return $summaries;
    }

    public function unreadTotal(User $me): int
    {
        return array_sum($this->unreadMap($me, $this->canSupport($me) || $this->isClient($me) ? $this->conversationsFor($me) : []));
    }

    /**
     * @return array{messages: list<array<string, mixed>>, readUpTo: int, hasMore: bool}
     */
    public function thread(Conversation $conversation, User $me, ?int $after = null, ?int $before = null): array
    {
        $limit = 50;
        if ($after === null && $before === null) {
            $this->purgeExpired(); // à l'ouverture d'une discussion : les documents périmés disparaissent
        }
        $messages = $this->messages->page($conversation, $after, $before, $limit);
        $this->preloadAttachments($messages);
        $readUpTo = $this->readUpTo($conversation, $me);

        return [
            'messages' => array_map(fn (Message $m) => $this->serialize($m, $me, $readUpTo), $messages),
            'readUpTo' => $readUpTo,
            'hasMore'  => $after === null && count($messages) === $limit,
        ];
    }

    /** Jusqu'à quel message les AUTRES participants ont lu (pour les coches « lu »). */
    public function readUpTo(Conversation $conversation, User $me): int
    {
        $max = 0;
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getUser()->getId() !== $me->getId()) {
                $max = max($max, $participant->getLastReadMessageId());
            }
        }

        return $max;
    }

    /**
     * Marque comme lu tout ce que le navigateur a effectivement affiché (jamais au-delà :
     * un message arrivé entre-temps reste « non lu »).
     */
    public function markRead(Conversation $conversation, User $me, int $upTo): void
    {
        $participant = $this->participation($conversation, $me);
        if ($participant !== null && $upTo > $participant->getLastReadMessageId()) {
            $participant->markReadUpTo($upTo);
            $this->em->flush();
        }
    }

    /** @return array<string, mixed> */
    public function serialize(Message $message, User $me, int $readUpTo = 0): array
    {
        $author = $message->getAuthor();
        $mine   = $author !== null && $author->getId() === $me->getId();

        return [
            'id'         => $message->getId(),
            'body'       => $message->getBody(),
            'at'         => $message->getCreatedAt()->format(\DATE_ATOM),
            'mine'       => $mine,
            'authorId'   => $author?->getId(),
            'staff'      => $author !== null && $author->isStaff(),
            'authorName' => $this->authorLabel($message, $author, $me),
            'read'       => $mine && $message->getId() <= $readUpTo,
            'attachment' => $this->serializeAttachment($this->attachmentOf($message)),
        ];
    }

    /** Dans une discussion de support, le client ne voit jamais qui, au club, lui répond : toujours « Support ». */
    private function authorLabel(Message $message, ?User $author, User $me): string
    {
        if ($author !== null && $message->getConversation()->isSupport() && $author->isStaff() && !$me->isStaff()) {
            return 'Support';
        }

        return $author?->getNomComplet() ?: 'Compte supprimé';
    }

    /** @return array<string, mixed>|null */
    private function serializeAttachment(?ChatAttachment $attachment): ?array
    {
        if ($attachment === null) {
            return null;
        }
        $ext = strtolower(pathinfo($attachment->getName(), \PATHINFO_EXTENSION));

        return [
            'id'        => $attachment->getId(),
            'name'      => $attachment->getName(),
            'size'      => $attachment->getSize(),
            'kind'      => $this->storage->kindOf($ext),
            'expiresAt' => $attachment->getExpiresAt()->format(\DATE_ATOM),
            'purgedAt'  => $attachment->getPurgedAt()?->format(\DATE_ATOM),
            'available' => $this->fileOf($attachment) !== null,
        ];
    }

    // -- Documents joints ------------------------------------------------------

    /** @param list<Message> $messages */
    private function preloadAttachments(array $messages): void
    {
        $ids = [];
        foreach ($messages as $message) {
            if ($message->getId() !== null && !array_key_exists($message->getId(), $this->attachments)) {
                $ids[] = $message->getId();
            }
        }
        $found = $this->attachmentRepository->byMessages($ids);
        foreach ($ids as $id) {
            $this->attachments[$id] = $found[$id] ?? null;
        }
    }

    private function attachmentOf(Message $message): ?ChatAttachment
    {
        if (!array_key_exists((int) $message->getId(), $this->attachments)) {
            $this->preloadAttachments([$message]);
        }

        return $this->attachments[(int) $message->getId()] ?? null;
    }

    /** Chemin réel du fichier si le document est encore disponible (non périmé, toujours présent), sinon null. */
    public function fileOf(ChatAttachment $attachment): ?string
    {
        if ($attachment->isExpired()) {
            return null;
        }
        try {
            $resolved = $this->storage->resolve($attachment->getPath());
        } catch (FileManagerException) {
            return null;
        }

        return $resolved->exists && $resolved->real !== null && is_file($resolved->real) ? $resolved->real : null;
    }

    /** Envoie un fichier de l'appareil : il rejoint l'espace de l'expéditeur et sera supprimé à l'échéance. */
    public function sendUpload(Conversation $conversation, User $author, UploadedFile $file, string $comment = ''): Message
    {
        $this->assertCanSendTo($conversation, $author);
        if (!$author->isStaff()) {
            $ext = strtolower($file->getClientOriginalExtension());
            if ($file->getSize() > self::MEMBER_MAX_BYTES || !in_array($ext, self::MEMBER_EXTENSIONS, true)) {
                throw new ChatException(sprintf('Document refusé : formats courants (PDF, images, Office) de %d Mo maximum.', self::MEMBER_MAX_BYTES / 1048576));
            }
        }

        try {
            $virtual = $this->storage->upload($this->space->ensureTemp($author), $file);
            $real    = $this->storage->resolve($virtual)->real;
        } catch (FileManagerException $e) {
            throw new ChatException($e->getMessage());
        }

        return $this->attach($conversation, $author, $comment, basename($virtual), $virtual, (int) filesize((string) $real), true);
    }

    /** Partage un document déjà présent dans l'espace de l'expéditeur (le fichier d'origine n'est jamais supprimé). */
    public function shareExisting(Conversation $conversation, User $author, string $virtual, string $comment = ''): Message
    {
        $this->assertCanSendTo($conversation, $author);
        try {
            $resolved = $this->storage->resolve($virtual);
        } catch (FileManagerException) {
            throw new ChatException('Document introuvable.');
        }
        if (!$this->space->isOwn($author, $resolved->virtual) || !$resolved->exists || $resolved->real === null || !is_file($resolved->real)) {
            throw new ChatException('Vous ne pouvez partager qu\'un document de votre propre espace.');
        }

        return $this->attach($conversation, $author, $comment, basename($resolved->virtual), $resolved->virtual, (int) filesize($resolved->real), false);
    }

    private function attach(Conversation $conversation, User $author, string $comment, string $name, string $virtual, int $size, bool $temporary): Message
    {
        $comment = trim(preg_replace("/\r\n?/", "\n", $comment) ?? '');
        if (mb_strlen($comment) > Message::MAX_LENGTH) {
            throw new ChatException(sprintf('Message trop long (%d caractères maximum).', Message::MAX_LENGTH));
        }

        $message = new Message($conversation, $author, $comment);
        $conversation->setUpdatedAt($message->getCreatedAt());
        $this->em->persist($message);
        $this->em->persist(new ChatAttachment($message, $author, $name, $virtual, $size, $temporary));
        $this->em->flush();

        $conversation->participantFor($author)?->markReadUpTo((int) $message->getId());
        $this->em->flush();
        $this->notifier->notify($message);

        return $message;
    }

    private function assertCanSendTo(Conversation $conversation, User $author): void
    {
        if ($this->participation($conversation, $author) === null) {
            throw new ChatException('Vous ne faites pas partie de cette discussion.');
        }
    }

    /**
     * Documents de l'espace personnel que l'utilisateur peut partager (hors fichiers temporaires de la messagerie).
     *
     * @return list<array{name: string, path: string, size: int, mtime: int}>
     */
    public function shareableDocuments(User $me): array
    {
        $root  = $this->space->ensure($me);
        $files = [];
        foreach ($this->storage->filesUnder($root) as $entry) {
            if (str_starts_with($entry['path'], $this->space->tempPath($me).'/')) {
                continue;
            }
            $files[] = ['name' => $entry['name'], 'path' => $entry['path'], 'size' => (int) $entry['size'], 'mtime' => (int) $entry['mtime']];
        }
        usort($files, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return array_slice($files, 0, 200);
    }

    /**
     * Supprime du disque les documents temporaires arrivés à échéance et ferme l'accès aux documents partagés.
     * Les lignes sont conservées : l'historique garde la trace de l'échange.
     */
    public function purgeExpired(): int
    {
        $count = 0;
        foreach ($this->attachmentRepository->expired() as $attachment) {
            if ($attachment->isTemporary()) {
                try {
                    $resolved = $this->storage->resolve($attachment->getPath());
                    if ($resolved->exists && $resolved->real !== null && is_file($resolved->real)) {
                        @unlink($resolved->real);
                    }
                } catch (FileManagerException) {
                    // déjà supprimé à la main : rien à faire
                }
            }
            $attachment->markPurged();
            ++$count;
        }
        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    // -- Écriture --------------------------------------------------------------

    public function send(Conversation $conversation, User $author, string $body): Message
    {
        $body = trim(preg_replace("/\r\n?/", "\n", $body) ?? '');
        if ($body === '') {
            throw new ChatException('Le message est vide.');
        }
        if (mb_strlen($body) > Message::MAX_LENGTH) {
            throw new ChatException(sprintf('Message trop long (%d caractères maximum).', Message::MAX_LENGTH));
        }
        if ($this->participation($conversation, $author) === null) {
            throw new ChatException('Vous ne faites pas partie de cette discussion.');
        }

        $message = new Message($conversation, $author, $body);
        $conversation->setUpdatedAt($message->getCreatedAt());
        $this->em->persist($message);
        $this->em->flush();

        // Ce qu'on vient d'écrire est forcément lu par soi-même.
        $conversation->participantFor($author)?->markReadUpTo((int) $message->getId());
        $this->em->flush();
        $this->notifier->notify($message);

        return $message;
    }

    /**
     * Ouvre une discussion (ou retrouve celle qui existe déjà entre les deux mêmes personnes).
     *
     * @param list<User> $others
     */
    public function start(User $me, array $others, ?string $subject = null, ?string $firstMessage = null): Conversation
    {
        $others = array_values(array_filter(
            array_unique($others, \SORT_REGULAR),
            static fn (User $u) => $u->getId() !== $me->getId(),
        ));
        if ($this->isClient($me)) {
            throw new ChatException('Pour contacter le club, utilisez la messagerie « Support ».');
        }
        if ($others === []) {
            throw new ChatException('Choisissez au moins un destinataire.');
        }
        if ($me->isStaff() && !$this->permissions->can('messagerie.utiliser', $me) && !($this->canSupport($me) && count($others) === 1 && $this->isClient($others[0]))) {
            throw new ChatException('Votre profil permet uniquement de répondre au support client.');
        }
        // Un client n'a qu'un seul interlocuteur : « Support ». Écrire à un client, c'est ouvrir (ou reprendre) sa discussion de support.
        if ($me->isStaff() && count($others) === 1 && $this->isClient($others[0])) {
            if (!$this->canSupport($me)) {
                throw new ChatException('Vous n\'avez pas l\'autorisation de contacter les clients (support client).');
            }
            $conversation = $this->supportConversationFor($others[0]);
            if ($firstMessage !== null && trim($firstMessage) !== '') {
                $this->send($conversation, $me, $firstMessage);
            }

            return $conversation;
        }
        if (count($others) + 1 > self::MAX_GROUP_SIZE) {
            throw new ChatException(sprintf('Une discussion est limitée à %d personnes.', self::MAX_GROUP_SIZE));
        }
        if (!$me->isStaff()) {
            if (count($others) !== 1 || !$this->isContactableStaff($others[0])) {
                throw new ChatException('Vous pouvez uniquement écrire à un administrateur du club.');
            }
        }
        foreach ($others as $other) {
            if ($other->isStaff() && !$this->canUseMessaging($other)) {
                throw new ChatException(sprintf('%s n\'a pas accès à la messagerie.', $other->getNomComplet() ?: 'Ce compte'));
            }
        }

        $isGroup = count($others) > 1;
        $conversation = !$isGroup ? $this->conversations->findDirect($me, $others[0]) : null;

        if ($conversation === null) {
            $conversation = (new Conversation())->setIsGroup($isGroup);
            if ($isGroup) {
                $conversation->setSubject($subject);
            }
            $conversation->addParticipant($me);
            foreach ($others as $other) {
                $conversation->addParticipant($other);
            }
            $this->em->persist($conversation);
            $this->em->flush();
        }

        if ($firstMessage !== null && trim($firstMessage) !== '') {
            $this->send($conversation, $me, $firstMessage);
        }

        return $conversation;
    }

    // -- Contacts --------------------------------------------------------------

    /**
     * Personnes que l'utilisateur peut contacter (équipe du club pour un joueur ; tout le monde pour l'équipe).
     *
     * @return list<array{id: int, name: string, role: string, initials: string, staff: bool}>
     */
    public function contacts(User $me): array
    {
        $contacts = [];
        if ($this->isClient($me)) {
            return [];
        }
        $onlyClients = $me->isStaff() && !$this->permissions->can('messagerie.utiliser', $me);
        foreach ($this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']) as $user) {
            if ($user->getId() === $me->getId()) {
                continue;
            }
            if ($onlyClients && !$this->isClient($user)) {
                continue;
            }
            if ($this->isClient($user) && !$this->canSupport($me)) {
                continue;
            }
            // Un compte de l'équipe sans l'autorisation « messagerie.utiliser » est invisible pour tout le monde.
            if ($user->isStaff() && !$this->canUseMessaging($user)) {
                continue;
            }
            // Un joueur / une famille ne voit que les administrateurs, jamais un autre joueur ni une autre famille.
            if (!$me->isStaff() && !$this->isContactableStaff($user)) {
                continue;
            }
            $contacts[] = [
                'id'       => $user->getId(),
                'name'     => $user->getNomComplet() ?: (string) $user->getEmail(),
                'role'     => $this->roleLabel($user),
                'initials' => $this->initials($user),
                'staff'    => $user->isStaff(),
            ];
        }

        return $contacts;
    }

    /** Pour un joueur / une famille : uniquement les administrateurs, jamais un simple encadrant ni un autre licencié. */
    private function isContactableStaff(User $user): bool
    {
        return (bool) array_intersect($user->getRoles(), ['ROLE_ADMIN', 'ROLE_DEV']);
    }

    /** Un compte de l'équipe doit avoir l'autorisation « messagerie.utiliser » pour être contacté ou contacter quelqu'un. */
    private function canUseMessaging(User $user): bool
    {
        return !$user->isStaff() || $this->permissions->can('messagerie.utiliser', $user);
    }

    public function roleLabel(User $user): string
    {
        return match (true) {
            \in_array('ROLE_DEV', $user->getRoles(), true)    => 'Développeur',
            \in_array('ROLE_ADMIN', $user->getRoles(), true)  => 'Administrateur',
            \in_array('ROLE_EDITOR', $user->getRoles(), true) => 'Encadrement',
            $this->isClient($user)                             => 'Client',
            default                                            => 'Famille / licencié',
        };
    }

    // -- Utilitaires -----------------------------------------------------------

    /** @return list<User> */
    /** Participants d'une discussion de groupe (pour la liste affichée depuis l'en-tête). @return list<array{name: string, me: bool, staff: bool}> */
    private function people(Conversation $conversation, User $me): array
    {
        $people = [];
        foreach ($conversation->getParticipants() as $participant) {
            $user     = $participant->getUser();
            $people[] = ['name' => $user->getNomComplet(), 'me' => $user->getId() === $me->getId(), 'staff' => $user->isStaff()];
        }
        usort($people, static fn (array $a, array $b) => [$b['me'], $a['name']] <=> [$a['me'], $b['name']]);

        return $people;
    }

    /**
     * L'interlocuteur d'une discussion à deux (null pour un groupe). Dans une discussion de support : le client vu par l'équipe,
     * personne en particulier vu par le client.
     *
     * @param list<User> $others
     */
    private function peerOf(Conversation $conversation, User $me, array $others): ?User
    {
        if ($conversation->isSupport()) {
            return $me->isStaff() ? $conversation->getCustomer() : null;
        }

        return !$conversation->isGroup() ? ($others[0] ?? null) : null;
    }

    private function others(Conversation $conversation, User $me): array
    {
        $others = [];
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getUser()->getId() !== $me->getId()) {
                $others[] = $participant->getUser();
            }
        }

        return $others;
    }

    private function title(Conversation $conversation, User $me): string
    {
        if ($conversation->isGroup()) {
            if ($conversation->getSubject()) {
                return $conversation->getSubject();
            }
            $names = array_map(static fn (User $u) => $u->getPrenom() ?: $u->getNom(), $this->others($conversation, $me));

            return implode(', ', array_slice($names, 0, 3)).(count($names) > 3 ? '…' : '');
        }

        if ($conversation->isSupport() && !$me->isStaff()) {
            return 'Support ES Coutances';
        }
        $peer = $this->peerOf($conversation, $me, $this->others($conversation, $me));

        return $peer?->getNomComplet() ?: ($peer?->getEmail() ?? 'Compte supprimé');
    }

    private function initials(?User $user): string
    {
        if ($user === null) {
            return '?';
        }
        $first = mb_substr($user->getPrenom() ?: $user->getNom() ?: '?', 0, 1);
        $last  = $user->getPrenom() && $user->getNom() ? mb_substr($user->getNom(), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    private function preview(Message $message, User $me, bool $group): string
    {
        $text   = trim(preg_replace('/\s+/', ' ', $message->getBody()) ?? '');
        if (($attachment = $this->attachmentOf($message)) !== null) {
            $text = '📎 '.$attachment->getName().($text !== '' ? ' — '.$text : '');
        }
        $text   = mb_strlen($text) > 90 ? mb_substr($text, 0, 90).'…' : $text;
        $author = $message->getAuthor();

        if ($author !== null && $author->getId() === $me->getId()) {
            return 'Vous : '.$text;
        }

        return $group && $author !== null ? ($author->getPrenom() ?: $author->getNom()).' : '.$text : $text;
    }
}

<?php

namespace App\Service\Chat;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Security\PermissionChecker;
use Doctrine\ORM\EntityManagerInterface;

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

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConversationRepository $conversations,
        private readonly MessageRepository $messages,
        private readonly PermissionChecker $permissions,
    ) {
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
        $conversations = $this->conversations->findForUser($me);
        $ids           = array_map(static fn (Conversation $c) => (int) $c->getId(), $conversations);
        $last          = $this->messages->lastOf($ids);
        $unread        = $this->conversations->unreadCounts($me, $ids);

        $summaries = [];
        foreach ($conversations as $conversation) {
            $others  = $this->others($conversation, $me);
            $message = $last[$conversation->getId()] ?? null;
            $peer    = !$conversation->isGroup() ? ($others[0] ?? null) : null;

            $summaries[] = [
                'id'          => $conversation->getId(),
                'title'       => $this->title($conversation, $me),
                'initials'    => $conversation->isGroup() ? null : $this->initials($peer),
                'group'       => $conversation->isGroup(),
                'members'     => count($others) + 1,
                'people'      => $conversation->isGroup() ? $this->people($conversation, $me) : [],
                'online'      => $peer !== null && $this->isOnline($peer),
                'lastSeen'    => $peer?->getLastSeenAt()?->format(\DATE_ATOM),
                'avatar'      => $peer?->getAvatarName() ? '/uploads/avatars/'.$peer->getAvatarName() : null,
                'preview'     => $message ? $this->preview($message, $me, $conversation->isGroup()) : 'Nouvelle discussion',
                'previewAt'   => ($message?->getCreatedAt() ?? $conversation->getCreatedAt())->format(\DATE_ATOM),
                'unread'      => $unread[$conversation->getId()] ?? 0,
                'lastId'      => $message?->getId() ?? 0,
            ];
        }

        return $summaries;
    }

    public function unreadTotal(User $me): int
    {
        return array_sum($this->conversations->unreadCounts($me));
    }

    /**
     * @return array{messages: list<array<string, mixed>>, readUpTo: int, hasMore: bool}
     */
    public function thread(Conversation $conversation, User $me, ?int $after = null, ?int $before = null): array
    {
        $limit    = 50;
        $messages = $this->messages->page($conversation, $after, $before, $limit);
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
        $participant = $conversation->participantFor($me);
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
            'authorName' => $author?->getNomComplet() ?: 'Compte supprimé',
            'read'       => $mine && $message->getId() <= $readUpTo,
        ];
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
        if ($conversation->participantFor($author) === null) {
            throw new ChatException("Vous ne faites pas partie de cette discussion.");
        }

        $message = new Message($conversation, $author, $body);
        $conversation->setUpdatedAt($message->getCreatedAt());
        $this->em->persist($message);
        $this->em->flush();

        // Ce qu'on vient d'écrire est forcément lu par soi-même.
        $conversation->participantFor($author)?->markReadUpTo((int) $message->getId());
        $this->em->flush();

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
        if ($others === []) {
            throw new ChatException('Choisissez au moins un destinataire.');
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
        foreach ($this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']) as $user) {
            if ($user->getId() === $me->getId()) {
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

        $peer = $this->others($conversation, $me)[0] ?? null;

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
        $text   = mb_strlen($text) > 90 ? mb_substr($text, 0, 90).'…' : $text;
        $author = $message->getAuthor();

        if ($author !== null && $author->getId() === $me->getId()) {
            return 'Vous : '.$text;
        }

        return $group && $author !== null ? ($author->getPrenom() ?: $author->getNom()).' : '.$text : $text;
    }
}

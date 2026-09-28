<?php

namespace App\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Écrit dans le journal d'activité (table audit_log). Chaque ligne est enrichie du contexte de la
 * requête : compte connecté, adresse IP, navigateur, page, identifiant de requête. L'écriture passe
 * par DBAL (pas par l'ORM) pour ne jamais se déclencher elle-même, et une panne du journal ne doit
 * jamais faire échouer l'action de l'utilisateur : elle est seulement signalée dans les logs techniques.
 */
class AuditRecorder
{
    /** Clés dont la valeur n'est jamais enregistrée. */
    private const SENSITIVE = '/pass|pwd|token|secret|csrf|cookie|authorization|cardnumber|cvv|iban/i';

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed>|null $changes
     * @param array<string, mixed>|null $context
     */
    public function event(
        string $type,
        ?string $operation,
        string $category,
        string $summary,
        ?array $changes = null,
        ?array $context = null,
        ?string $entityClass = null,
        int|string|null $entityId = null,
        ?string $entityLabel = null,
        ?int $statusCode = null,
        ?User $actor = null,
        ?string $attemptedIdentifier = null,
    ): void {
        $this->write($this->row($type, $operation, $category, $summary, $changes, $context, $entityClass, $entityId, $entityLabel, $statusCode, $actor, $attemptedIdentifier));
    }

    /** @param array<string, mixed> $row ligne construite par row() */
    public function write(array $row): void
    {
        $this->writeMany([$row]);
    }

    /** @param list<array<string, mixed>> $rows */
    public function writeMany(array $rows): void
    {
        try {
            foreach ($rows as $row) {
                $this->connection->insert('audit_log', $row);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Journal d\'activité : écriture impossible — '.$e->getMessage());
        }
    }

    /**
     * Construit une ligne complète (contexte de la requête inclus).
     *
     * @param array<string, mixed>|null $changes
     * @param array<string, mixed>|null $context
     * @return array<string, mixed>
     */
    public function row(
        string $type,
        ?string $operation,
        string $category,
        string $summary,
        ?array $changes = null,
        ?array $context = null,
        ?string $entityClass = null,
        int|string|null $entityId = null,
        ?string $entityLabel = null,
        ?int $statusCode = null,
        ?User $actor = null,
        ?string $attemptedIdentifier = null,
    ): array {
        $request = $this->requestStack->getMainRequest();
        $user    = $actor ?? $this->currentUser();

        return [
            'occurred_at'  => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s'),
            'type'         => $type,
            'operation'    => $operation,
            'category'     => mb_substr($category, 0, 60),
            'summary'      => mb_substr($summary, 0, 500),
            'entity_class' => $entityClass,
            'entity_id'    => null === $entityId ? null : (string) $entityId,
            'entity_label' => null === $entityLabel ? null : mb_substr($entityLabel, 0, 255),
            'changes'      => null === $changes || [] === $changes ? null : json_encode($this->clean($changes), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'context'      => null === $context || [] === $context ? null : json_encode($this->clean($context), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'user_id'      => $user?->getId(),
            'user_email'   => $user?->getEmail() ?? $attemptedIdentifier,
            'user_name'    => $user?->getNomComplet(),
            'user_roles'   => $user ? mb_substr(implode(',', array_diff($user->getRoles(), ['ROLE_USER'])), 0, 200) : null,
            'ip'           => $request?->getClientIp(),
            'forwarded_for' => $request ? mb_substr((string) $request->headers->get('X-Forwarded-For'), 0, 255) ?: null : null,
            'user_agent'   => $request ? mb_substr((string) $request->headers->get('User-Agent'), 0, 500) ?: null : null,
            'method'       => $request?->getMethod(),
            'route'        => $request ? mb_substr((string) $request->attributes->get('_route'), 0, 120) ?: null : null,
            'path'         => $request ? mb_substr($request->getPathInfo(), 0, 500) : (PHP_SAPI === 'cli' ? mb_substr(implode(' ', $_SERVER['argv'] ?? []), 0, 500) : null),
            'status_code'  => $statusCode,
            'request_id'   => $this->requestId($request),
            'source'       => $request ? 'web' : 'cli',
        ];
    }

    /** Identifiant unique de la requête en cours (relie toutes ses lignes). */
    public function requestId(?Request $request = null): string
    {
        $request ??= $this->requestStack->getMainRequest();
        if (!$request) {
            return $this->cliId ??= bin2hex(random_bytes(16));
        }
        if (!$request->attributes->has('_audit_request_id')) {
            $request->attributes->set('_audit_request_id', bin2hex(random_bytes(16)));
        }

        return (string) $request->attributes->get('_audit_request_id');
    }

    private ?string $cliId = null;

    /** Journalise l'envoi (ou l'échec d'envoi) d'un e-mail : destinataires et objet, jamais le contenu. */
    public function mail(RawMessage $message, bool $ok, string $via, ?string $error = null): void
    {
        $to = $subject = null;
        if ($message instanceof Email) {
            $to      = array_map(static fn ($a) => $a->getAddress(), $message->getTo());
            $subject = $message->getSubject();
        }

        $this->event(
            AuditLog::TYPE_MAIL,
            $ok ? 'envoi' : 'echec',
            'E-mails',
            sprintf('« %s » à %s', $subject ?? 'sans objet', $to ? implode(', ', $to) : 'destinataire inconnu'),
            null,
            array_filter(['destinataires' => $to, 'objet' => $subject, 'serveur' => $via, 'erreur' => $error]),
        );
    }

    private function currentUser(): ?User
    {
        try {
            $user = $this->security->getUser();
        } catch (\Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    /** Valeur sûre pour le journal : secrets masqués, objets aplatis, textes tronqués. */
    public function clean(mixed $value, string $key = ''): mixed
    {
        if ('' !== $key && preg_match(self::SENSITIVE, $key)) {
            return '••••••';
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                $clean[$k] = $this->clean($v, (string) $k);
            }

            return $clean;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }
        if (is_object($value)) {
            return $value instanceof \Stringable ? mb_substr((string) $value, 0, 300) : (new \ReflectionClass($value))->getShortName();
        }
        if (is_string($value) && mb_strlen($value) > 1000) {
            return mb_substr($value, 0, 1000).'…';
        }

        return $value;
    }
}

<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne du journal d'activité : qui (compte, IP, navigateur) a fait quoi (action, élément,
 * valeurs avant / après), quand et depuis quelle page. Le journal est en ajout seul : aucun
 * écran ni code de l'application ne modifie ni ne supprime ces lignes. Elles sont écrites par
 * App\Audit\AuditRecorder, jamais par l'ORM (pas de lien vers les utilisateurs : la trace survit
 * à la suppression d'un compte).
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'IDX_AUDIT_DATE', columns: ['occurred_at'])]
#[ORM\Index(name: 'IDX_AUDIT_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_AUDIT_TYPE', columns: ['type', 'category'])]
#[ORM\Index(name: 'IDX_AUDIT_REQUEST', columns: ['request_id'])]
#[ORM\Index(name: 'IDX_AUDIT_ENTITY', columns: ['entity_class', 'entity_id'])]
class AuditLog
{
    /** Types de lignes. */
    public const TYPE_DONNEES   = 'donnees';   // création / modification / suppression d'un enregistrement
    public const TYPE_REQUETE   = 'requete';   // action demandée par un utilisateur (formulaire, bouton…)
    public const TYPE_SECURITE  = 'securite';  // connexion, échec, déconnexion, accès refusé
    public const TYPE_MAIL      = 'mail';      // e-mail envoyé ou échoué
    public const TYPE_SYSTEME   = 'systeme';   // commande, déploiement…

    public const TYPES = [
        self::TYPE_DONNEES  => 'Données',
        self::TYPE_REQUETE  => 'Actions',
        self::TYPE_SECURITE => 'Sécurité',
        self::TYPE_MAIL     => 'E-mails',
        self::TYPE_SYSTEME  => 'Système',
    ];

    public const OPERATIONS = [
        'creation'        => 'Création',
        'modification'    => 'Modification',
        'suppression'     => 'Suppression',
        'action'          => 'Action',
        'telechargement'  => 'Téléchargement',
        'connexion'       => 'Connexion',
        'echec_connexion' => 'Échec de connexion',
        'deconnexion'     => 'Déconnexion',
        'refus'           => 'Accès refusé',
        'envoi'           => 'Envoi',
        'echec'           => 'Échec',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $occurredAt = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_REQUETE;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $operation = null;

    /** Rubrique du site (Licenciés, Boutique, Sécurité…). */
    #[ORM\Column(length: 60)]
    private string $category = 'Autre';

    #[ORM\Column(length: 500)]
    private string $summary = '';

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $entityClass = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityLabel = null;

    /** Valeurs modifiées : champ => [avant, après]. @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changes = null;

    /** Détails complémentaires (paramètres envoyés, destinataires, motif…). @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $userRoles = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $forwardedFor = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(nullable: true)]
    private ?int $statusCode = null;

    /** Identifiant partagé par toutes les lignes d'une même requête. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $requestId = null;

    #[ORM\Column(length: 10, options: ['default' => 'web'])]
    private string $source = 'web';

    public function getId(): ?string
    {
        return $this->id;
    }
    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }
    public function getType(): string
    {
        return $this->type;
    }
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
    public function getOperation(): ?string
    {
        return $this->operation;
    }
    public function getOperationLabel(): string
    {
        return self::OPERATIONS[$this->operation] ?? (string) $this->operation;
    }
    public function getCategory(): string
    {
        return $this->category;
    }
    public function getSummary(): string
    {
        return $this->summary;
    }
    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }
    public function getEntityId(): ?string
    {
        return $this->entityId;
    }
    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }
    /** @return array<string, mixed>|null */
    public function getChanges(): ?array
    {
        return $this->changes;
    }
    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }
    public function getUserName(): ?string
    {
        return $this->userName;
    }
    public function getUserRoles(): ?string
    {
        return $this->userRoles;
    }
    public function getIp(): ?string
    {
        return $this->ip;
    }
    public function getForwardedFor(): ?string
    {
        return $this->forwardedFor;
    }
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
    public function getMethod(): ?string
    {
        return $this->method;
    }
    public function getRoute(): ?string
    {
        return $this->route;
    }
    public function getPath(): ?string
    {
        return $this->path;
    }
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
    public function getSource(): string
    {
        return $this->source;
    }

    /** Nom court de la classe concernée (Famille, Article…). */
    public function getEntityShort(): ?string
    {
        return $this->entityClass ? substr(strrchr('\\'.$this->entityClass, '\\'), 1) : null;
    }
}

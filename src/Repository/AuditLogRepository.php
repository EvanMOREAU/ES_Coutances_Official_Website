<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
class AuditLogRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 50;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Lignes du journal filtrées, les plus récentes d'abord.
     *
     * @param array<string, string> $filters q, user, type, operation, category, ip, entity, entityId, from, to, errors, request
     * @return array{rows: list<AuditLog>, total: int}
     */
    public function search(array $filters, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $qb = $this->filtered($filters);

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $rows  = $qb->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $perPage)->setMaxResults($perPage)
            ->getQuery()->getResult();

        return ['rows' => $rows, 'total' => $total];
    }

    /** @param array<string, string> $filters @return iterable<AuditLog> pour l'export, plafonné */
    public function export(array $filters, int $limit = 20000): iterable
    {
        return $this->filtered($filters)->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->toIterable();
    }

    /** @return list<AuditLog> toutes les lignes d'une même requête, dans l'ordre chronologique */
    public function forRequest(string $requestId): array
    {
        return $this->createQueryBuilder('a')->andWhere('a.requestId = :r')->setParameter('r', $requestId)
            ->orderBy('a.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<array{id: int, email: string, name: ?string}> comptes présents dans le journal (filtre « utilisateur ») */
    public function users(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.userId AS id, a.userEmail AS email, MAX(a.userName) AS name')
            ->andWhere('a.userEmail IS NOT NULL')
            ->groupBy('a.userId, a.userEmail')->orderBy('a.userEmail', 'ASC')
            ->getQuery()->getArrayResult();
    }

    /** @return list<string> */
    public function usedCategories(): array
    {
        return array_column($this->createQueryBuilder('a')->select('DISTINCT a.category AS c')->orderBy('a.category', 'ASC')->getQuery()->getArrayResult(), 'c');
    }

    /** @param array<string, string> $filters */
    private function filtered(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');

        if ('' !== ($q = trim($filters['q'] ?? ''))) {
            $qb->andWhere('a.summary LIKE :q OR a.entityLabel LIKE :q OR a.userEmail LIKE :q OR a.userName LIKE :q OR a.path LIKE :q OR a.ip LIKE :q OR a.context LIKE :q OR a.changes LIKE :q')
                ->setParameter('q', '%'.addcslashes($q, '%_').'%');
        }
        if ('' !== ($user = $filters['user'] ?? '')) {
            $qb->andWhere('a.userEmail = :user')->setParameter('user', $user);
        }
        foreach (['type' => 'type', 'operation' => 'operation', 'category' => 'category'] as $key => $field) {
            if ('' !== ($filters[$key] ?? '')) {
                $qb->andWhere('a.'.$field.' = :'.$key)->setParameter($key, $filters[$key]);
            }
        }
        if ('' !== ($ip = trim($filters['ip'] ?? ''))) {
            $qb->andWhere('a.ip LIKE :ip')->setParameter('ip', $ip.'%');
        }
        if ('' !== ($entity = trim($filters['entity'] ?? ''))) {
            $qb->andWhere('a.entityClass LIKE :entity')->setParameter('entity', '%\\'.$entity);
        }
        if ('' !== ($id = trim($filters['entityId'] ?? ''))) {
            $qb->andWhere('a.entityId = :entityId')->setParameter('entityId', $id);
        }
        if ($from = $this->date($filters['from'] ?? null)) {
            $qb->andWhere('a.occurredAt >= :from')->setParameter('from', $from);
        }
        if ($to = $this->date($filters['to'] ?? null)) {
            $qb->andWhere('a.occurredAt < :to')->setParameter('to', $to->modify('+1 day'));
        }
        if (!empty($filters['errors'])) {
            $qb->andWhere("a.statusCode >= 400 OR a.operation IN ('echec', 'echec_connexion', 'refus')");
        }
        if ('' !== ($request = $filters['request'] ?? '')) {
            $qb->andWhere('a.requestId = :request')->setParameter('request', $request);
        }

        return $qb;
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}

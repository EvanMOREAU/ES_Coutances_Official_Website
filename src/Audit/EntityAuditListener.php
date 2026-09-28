<?php

namespace App\Audit;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;

/**
 * Journalise chaque création, modification et suppression d'enregistrement, avec les valeurs avant / après,
 * quelle que soit l'origine (admin, espace licencié, boutique, import, ligne de commande).
 *
 * Deux temps : onFlush relève ce qui change (l'identifiant d'une création n'existe pas encore) ;
 * postFlush écrit les lignes une fois les identifiants connus.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class EntityAuditListener
{
    /** @var array<int, array{op: string, entity: ?object, class: string, id: string|int|null, label: ?string, changes: array<string, mixed>}> */
    private array $pending = [];

    public function __construct(private readonly AuditRecorder $recorder)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em  = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->ignored($entity)) {
                continue;
            }
            $changes = [];
            foreach ($uow->getEntityChangeSet($entity) as $field => [, $new]) {
                if (null !== $new && [] !== $new && !in_array($field, AuditCatalog::IGNORED_FIELDS, true)) {
                    $changes[$field] = [null, $this->value($new)];
                }
            }
            $this->pending[spl_object_id($entity)] = ['op' => 'creation', 'entity' => $entity, 'class' => $entity::class, 'id' => null, 'label' => null, 'changes' => $changes];
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->ignored($entity)) {
                continue;
            }
            $changes = [];
            foreach ($uow->getEntityChangeSet($entity) as $field => [$old, $new]) {
                if (!in_array($field, AuditCatalog::IGNORED_FIELDS, true) && $this->value($old) !== $this->value($new)) {
                    $changes[$field] = [$this->value($old), $this->value($new)];
                }
            }
            if ([] !== $changes) {
                $this->pending[spl_object_id($entity)] = ['op' => 'modification', 'entity' => $entity, 'class' => $entity::class, 'id' => $this->identifier($em, $entity), 'label' => $this->label($entity), 'changes' => $changes];
            }
        }

        // Relations plusieurs-à-plusieurs (ex. équipes d'un licencié) : éléments ajoutés / retirés.
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            if (!$collection instanceof PersistentCollection || !($owner = $collection->getOwner()) || $this->ignored($owner)) {
                continue;
            }
            $added   = array_map($this->value(...), $collection->getInsertDiff());
            $removed = array_map($this->value(...), $collection->getDeleteDiff());
            if ([] === $added && [] === $removed) {
                continue;
            }
            $key = spl_object_id($owner);
            $this->pending[$key] ??= ['op' => 'modification', 'entity' => $owner, 'class' => $owner::class, 'id' => $this->identifier($em, $owner), 'label' => $this->label($owner), 'changes' => []];
            $this->pending[$key]['changes'][$collection->getMapping()->fieldName ?? 'collection'] = array_filter(['ajouté' => $added, 'retiré' => $removed]);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->ignored($entity)) {
                continue;
            }
            $before = [];
            foreach ($uow->getOriginalEntityData($entity) as $field => $value) {
                if (null !== $value && !in_array($field, AuditCatalog::IGNORED_FIELDS, true)) {
                    $before[$field] = [$this->value($value), null];
                }
            }
            $this->pending[spl_object_id($entity)] = ['op' => 'suppression', 'entity' => null, 'class' => $entity::class, 'id' => $this->identifier($em, $entity), 'label' => $this->label($entity), 'changes' => $before];
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }

        $rows = [];
        foreach ($this->pending as $item) {
            $entity = $item['entity'];
            $id     = $item['id'] ?? (method_exists((object) $entity, 'getId') ? $entity?->getId() : null);
            $label  = $item['label'] ?? ($entity ? $this->label($entity) : null);
            [$name, $category] = AuditCatalog::entity($item['class']);
            $fields = 'modification' === $item['op'] ? ' (champs : '.implode(', ', array_keys($item['changes'])).')' : '';

            $rows[] = $this->recorder->row(
                AuditLog::TYPE_DONNEES,
                $item['op'],
                $category,
                sprintf('%s%s%s', $name, $label ? ' « '.$label.' »' : '', $fields),
                $item['changes'],
                null,
                $item['class'],
                $id,
                $label,
            );
        }
        $this->pending = [];
        $this->recorder->writeMany($rows);
    }

    private function ignored(object $entity): bool
    {
        return in_array(substr(strrchr('\\'.$entity::class, '\\'), 1), AuditCatalog::IGNORED_CLASSES, true);
    }

    private function identifier(EntityManagerInterface $em, object $entity): string|int|null
    {
        $id = $em->getUnitOfWork()->getEntityIdentifier($entity);

        return $id ? (string) reset($id) : null;
    }

    private function label(object $entity): ?string
    {
        return $entity instanceof \Stringable && '' !== trim((string) $entity) ? mb_substr(trim((string) $entity), 0, 200) : null;
    }

    /** Valeur lisible d'un champ (entité liée → « Classe #id (libellé) »). */
    private function value(mixed $value): mixed
    {
        if (is_object($value) && !$value instanceof \DateTimeInterface && !$value instanceof \UnitEnum && method_exists($value, 'getId')) {
            return sprintf('%s #%s%s', (new \ReflectionClass($value))->getShortName(), (string) $value->getId(), $value instanceof \Stringable ? ' ('.mb_substr((string) $value, 0, 80).')' : '');
        }

        return $this->recorder->clean($value);
    }
}

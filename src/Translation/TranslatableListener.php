<?php

namespace App\Translation;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Quand une entité à champs #[Translatable] est créée ou modifiée, ses textes sont traduits (et enregistrés en
 * base) juste après l'écriture : l'administrateur saisit en français, le site s'occupe du reste.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class TranslatableListener
{
    /** @var list<object> */
    private array $pending = [];

    /** @param ServiceLocator<ContentTranslator> $services Chargé à la demande : le traducteur dépend de l'EntityManager, qui dépend de ce listener. */
    public function __construct(#[AutowireLocator([ContentTranslator::class])] private readonly ServiceLocator $services)
    {
    }

    private function translator(): ContentTranslator
    {
        return $this->services->get(ContentTranslator::class);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->translator()->isAvailable()) {
            return;
        }
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ([$uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates()] as $entities) {
            foreach ($entities as $entity) {
                if ([] !== TranslatableFields::of($entity)) {
                    $this->pending[] = $entity;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pending) {
            return;
        }
        $entities      = $this->pending;
        $this->pending = [];
        $this->translator()->translateEntities($entities);
    }
}

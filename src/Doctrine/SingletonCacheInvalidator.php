<?php

namespace App\Doctrine;

use App\Entity\BoutiqueSettings;
use App\Entity\ContactSettings;
use App\Entity\HomepageBanner;
use App\Entity\MatchLive;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les « réglages » (une seule ligne) lus à chaque page sont gardés en cache pendant CACHE_TTL secondes. Dès qu'une
 * de ces lignes est enregistrée, modifiée ou supprimée, son cache est vidé : le site ne montre jamais un réglage périmé.
 *
 * Les repositories demandent le cache avec SingletonCacheInvalidator::cacheKey(Entity::class) (voir getSingleton()).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class SingletonCacheInvalidator
{
    public const CACHE_TTL = 300;

    /** Entités dont la lecture est mise en cache. */
    private const CACHED = [BoutiqueSettings::class, ContactSettings::class, HomepageBanner::class, MatchLive::class];

    /** @var array<string, true> clés à vider après l'écriture en base */
    private array $stale = [];

    public function __construct(
        #[Autowire(service: 'doctrine.result_cache_pool')] private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public static function cacheKey(string $entityClass): string
    {
        return 'singleton.'.strtr($entityClass, '\\', '.');
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ([$uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates(), $uow->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                foreach (self::CACHED as $class) {
                    if ($entity instanceof $class) {
                        $this->stale[self::cacheKey($class)] = true;
                    }
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] !== $this->stale) {
            $this->cache->deleteItems(array_keys($this->stale));
            $this->stale = [];
        }
    }
}

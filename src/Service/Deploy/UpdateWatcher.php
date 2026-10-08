<?php

namespace App\Service\Deploy;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Détecte en continu qu'une mise à jour est disponible, sans que personne n'ait à cliquer sur « Vérifier ».
 *
 * Deux temps, pour qu'une page ne soit jamais ralentie par le réseau :
 *  - le nombre de commits en attente est lu dans les références Git déjà connues (instantané, mis en
 *    cache 30 s) ;
 *  - ces références sont rafraîchies auprès de GitHub (`git fetch`) en arrière-plan, au plus une fois
 *    toutes les 10 minutes, par la commande `app:update:verifier` (que le cron peut aussi appeler).
 */
class UpdateWatcher
{
    private const COUNT_TTL = 30;
    private const FETCH_EVERY = 600;

    public function __construct(
        private readonly GitRepository $git,
        private readonly DeployService $deploy,
        private readonly CacheInterface $cache,
        private readonly string $kernelEnvironment,
    ) {
    }

    /** Nombre de mises à jour (commits) pas encore déployées d'après les références Git locales. */
    public function pendingCount(): int
    {
        if (!$this->git->isAvailable()) {
            return 0;
        }

        return $this->cache->get('update.pending', function (ItemInterface $item): int {
            $item->expiresAfter(self::COUNT_TTL);

            try {
                return count($this->git->pending($this->git->branch()));
            } catch (\RuntimeException) {
                return 0;
            }
        });
    }

    /** Lance, si la dernière vérification date, une interrogation de GitHub en arrière-plan. */
    public function refreshIfStale(): void
    {
        if ('test' === $this->kernelEnvironment || !$this->git->isAvailable()) {
            return;
        }

        $this->cache->get('update.fetch', function (ItemInterface $item): int {
            $item->expiresAfter(self::FETCH_EVERY);

            try {
                $this->deploy->spawnConsole('app:update:verifier');
            } catch (\Throwable) {
                // Impossible de lancer le processus : on réessaiera à la prochaine période.
            }

            return time();
        });
    }

    /** Interroge GitHub maintenant (appelé par `app:update:verifier`, dans un processus à part). */
    public function fetchNow(): int
    {
        $branch = $this->git->branch();
        $this->git->fetch($branch);
        $this->cache->delete('update.pending');

        return $this->pendingCount();
    }
}

<?php

namespace App\Controller;

use App\Service\Backup\DatabaseBackup;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point de contrôle pour la supervision (UptimeRobot, load balancer…) : répond 200 si le site fonctionne,
 * 503 sinon. Vérifie la base de données, l'écriture dans var/, l'espace disque et l'âge de la dernière
 * sauvegarde de la base ; n'expose que « ok » ou « error » par contrôle, jamais de détail interne.
 *
 * Une sauvegarde absente ou trop ancienne ne coupe pas le site (200) mais est signalée dans « checks »
 * (status « degraded ») pour qu'une alerte puisse la surveiller.
 */
final class HealthController extends AbstractController
{
    /** Espace disque libre minimal (fraction du total). */
    private const MIN_FREE_RATIO = 0.05;

    /** Âge maximal toléré de la dernière sauvegarde de la base (secondes). */
    private const MAX_BACKUP_AGE = 48 * 3600;

    public function __construct(
        private readonly DatabaseBackup $backups,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/health', name: 'app_health', methods: ['GET', 'HEAD'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        $critical = [
            'database' => $this->databaseWorks($connection),
            'storage'  => $this->storageWritable(),
            'disk'     => $this->diskHasRoom(),
        ];
        $backup = $this->backupIsRecent();

        if (in_array(false, $critical, true)) {
            return $this->noStore(new JsonResponse(['status' => 'error', 'checks' => $this->label($critical + ['backup' => $backup])], JsonResponse::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->noStore(new JsonResponse([
            'status' => $backup ? 'ok' : 'degraded',
            'checks' => $this->label($critical + ['backup' => $backup]),
        ]));
    }

    private function databaseWorks(Connection $connection): bool
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function storageWritable(): bool
    {
        return is_writable($this->projectDir.'/var');
    }

    private function diskHasRoom(): bool
    {
        $free  = @disk_free_space($this->projectDir);
        $total = @disk_total_space($this->projectDir);

        return false === $free || false === $total || 0.0 === (float) $total || ($free / $total) >= self::MIN_FREE_RATIO;
    }

    private function backupIsRecent(): bool
    {
        try {
            $all = $this->backups->all();

            return [] !== $all && $all[0]['createdAt']->getTimestamp() >= time() - self::MAX_BACKUP_AGE;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, bool> $checks
     *
     * @return array<string, string>
     */
    private function label(array $checks): array
    {
        return array_map(static fn (bool $ok): string => $ok ? 'ok' : 'error', $checks);
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}

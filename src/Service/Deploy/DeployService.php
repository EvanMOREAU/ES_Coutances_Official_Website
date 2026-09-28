<?php

namespace App\Service\Deploy;

use App\Entity\Deployment;
use App\Entity\User;
use App\Repository\DeploymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Mise à jour du site depuis le dépôt Git (GitHub) en un clic.
 *
 * Le déploiement est lancé dans un processus indépendant (`app:deploy <id>`),
 * pour ne pas dépendre de la durée de la requête web et survivre au vidage du
 * cache. Sa progression est écrite dans l'entité Deployment, que l'interface
 * relit toutes les secondes.
 *
 * Étapes : récupération du code (fast-forward uniquement, jamais de fusion
 * automatique) → dépendances Composer → migrations → styles → cache.
 * Le changelog vient du fichier changelog/releases.yaml, déployé avec le code.
 */
class DeployService
{
    /** Au-delà, un déploiement resté « en cours » est considéré comme mort (processus tué). */
    private const STALE_AFTER = 'PT30M';

    public function __construct(
        private readonly GitRepository $git,
        private readonly DeploymentRepository $deployments,
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
        private readonly string $kernelEnvironment,
    ) {
    }

    /**
     * État actuel du site vis-à-vis du dépôt distant.
     *
     * @return array<string, mixed>
     */
    public function status(bool $fetch = false): array
    {
        $status = [
            'available' => $this->git->isAvailable(),
            'error'     => null,
            'branch'    => null,
            'current'   => null,
            'pending'   => [],
            'dirty'     => false,
            'checked'   => $fetch,
        ];

        if (!$status['available']) {
            $status['error'] = "Git n'est pas disponible sur ce serveur (ou le site n'a pas été installé depuis un dépôt Git).";

            return $status;
        }

        try {
            $status['branch']  = $this->git->branch();
            $status['current'] = $this->git->currentCommit();
            $status['dirty']   = $this->git->isDirty();
            if ($fetch) {
                $this->git->fetch($status['branch']);
                $status['pending'] = $this->git->pending($status['branch']);
            } else {
                $status['pending'] = $this->safePending($status['branch']);
                $status['checked'] = false;
            }
        } catch (\RuntimeException $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /** Commits en attente d'après les références déjà connues (sans contacter GitHub). */
    private function safePending(string $branch): array
    {
        try {
            return $this->git->pending($branch);
        } catch (\RuntimeException) {
            return [];
        }
    }

    public function running(): ?Deployment
    {
        $running = $this->deployments->findRunning();
        if ($running !== null && $running->getStartedAt() < (new \DateTimeImmutable())->sub(new \DateInterval(self::STALE_AFTER))) {
            $running->setStatus(Deployment::STATUS_FAILED)->setFinishedAt(new \DateTimeImmutable());
            $running->appendLog("\n✗ Déploiement abandonné : aucune activité depuis plus de 30 minutes.\n");
            $this->em->flush();

            return null;
        }

        return $running;
    }

    /**
     * Crée le déploiement et lance le processus qui l'exécute.
     *
     * @throws \RuntimeException s'il n'y a rien à faire ou si un déploiement est déjà en cours
     */
    public function start(?User $user): Deployment
    {
        if ($this->running() !== null) {
            throw new \RuntimeException('Un déploiement est déjà en cours.');
        }

        $deployment = (new Deployment())
            ->setTriggeredBy($user?->getNomComplet() ?: $user?->getUserIdentifier())
            ->setFromCommit($this->git->head())
            ->setStep('Démarrage');
        $this->em->persist($deployment);
        $this->em->flush();

        $this->spawn($deployment);

        return $deployment;
    }

    private function spawn(Deployment $deployment): void
    {
        $php = (new PhpExecutableFinder())->find(false) ?: 'php';
        $arguments = sprintf('%s %s app:deploy %d --env=%s',
            escapeshellarg($php),
            escapeshellarg($this->projectDir.'/bin/console'),
            $deployment->getId(),
            escapeshellarg($this->kernelEnvironment),
        );

        // Détaché du processus web : la commande rend la main immédiatement.
        $command = \PHP_OS_FAMILY === 'Windows'
            ? sprintf('start /B "" %s > NUL 2>&1', $arguments)
            : sprintf('nohup %s > /dev/null 2>&1 < /dev/null &', $arguments);

        $process = Process::fromShellCommandline($command, $this->projectDir, null, null, 20);
        $process->run();
    }

    // -- Exécution (dans le processus `app:deploy`) -----------------------------

    /**
     * Enchaîne les étapes. Chaque sortie est ajoutée au journal du déploiement.
     */
    public function run(Deployment $deployment): bool
    {
        $php     = (new PhpExecutableFinder())->find(false) ?: 'php';
        $console = [$php, $this->projectDir.'/bin/console', '--no-interaction', '--env='.$this->kernelEnvironment];
        $isProd  = $this->kernelEnvironment === 'prod';

        try {
            $branch = $this->git->branch();
            $from   = $this->git->head();
            $deployment->setFromCommit($from);

            $this->step($deployment, 'Récupération du code');
            $this->log($deployment, "Dépôt distant : origin/{$branch}");
            $this->git->fetch($branch);

            $commits = $this->git->pending($branch);
            if ($commits === []) {
                $this->log($deployment, 'Le site est déjà à jour : rien à déployer.');
                $this->finish($deployment, Deployment::STATUS_SUCCESS);

                return true;
            }
            if ($this->git->isDirty()) {
                throw new \RuntimeException('Des fichiers du serveur ont été modifiés à la main : mise à jour interrompue pour ne rien écraser (voir « git status »).');
            }

            $this->log($deployment, sprintf('%d commit(s) à appliquer :', count($commits)));
            foreach (array_reverse($commits) as $commit) {
                $this->log($deployment, sprintf('  · %s  %s', $commit['short'], $commit['subject']));
            }
            $this->git->fastForwardTo($branch);
            $deployment->setToCommit($this->git->head());
            $this->log($deployment, 'Code à jour : '.$this->git->shortHash($deployment->getToCommit()).'.');

            $this->step($deployment, 'Dépendances PHP');
            $composer = $this->composerCommand();
            $this->exec($deployment, [...$composer, 'install', '--no-interaction', '--no-progress', ...($isProd ? ['--no-dev', '--optimize-autoloader'] : [])], 900);

            $this->step($deployment, 'Migrations de la base de données');
            $this->exec($deployment, [...$console, 'doctrine:migrations:migrate', '--allow-no-migration'], 600);

            $this->step($deployment, 'Compilation des styles');
            $this->exec($deployment, [...$console, 'tailwind:build', ...($isProd ? ['--minify'] : [])], 300);

            if ($isProd) {
                $this->step($deployment, 'Compilation des assets');
                $this->exec($deployment, [...$console, 'asset-map:compile'], 300);
            }

            $this->step($deployment, 'Vidage du cache');
            $this->exec($deployment, [...$console, 'cache:clear'], 300);

            $this->step($deployment, 'Terminé');
            $this->log($deployment, "\n✓ Mise à jour déployée avec succès.");
            $this->finish($deployment, Deployment::STATUS_SUCCESS);

            return true;
        } catch (\Throwable $e) {
            $this->log($deployment, "\n✗ Échec à l'étape « ".$deployment->getStep().' » : '.$e->getMessage());
            if ($deployment->getToCommit() !== null) {
                $this->log($deployment, sprintf(
                    "Le code est déjà à jour (%s). Pour revenir en arrière : git reset --hard %s, puis composer install et cache:clear.",
                    $this->git->shortHash($deployment->getToCommit()),
                    $this->git->shortHash($deployment->getFromCommit()),
                ));
            }
            $this->finish($deployment, Deployment::STATUS_FAILED);

            return false;
        }
    }

    /** @return list<string> */
    private function composerCommand(): array
    {
        $configured = $_SERVER['COMPOSER_BIN'] ?? $_ENV['COMPOSER_BIN'] ?? null;
        $finder     = new ExecutableFinder();
        $php        = (new PhpExecutableFinder())->find(false) ?: 'php';

        foreach (array_filter([$configured, 'composer', 'composer.phar']) as $candidate) {
            $path = is_file((string) $candidate) ? $candidate : $finder->find((string) $candidate);
            if ($path !== null) {
                // Un .phar ou un script se lance avec le même PHP que le site.
                return str_ends_with($path, '.phar') || !is_executable($path) ? [$php, $path] : [$path];
            }
        }

        throw new \RuntimeException("Composer est introuvable : définissez COMPOSER_BIN dans .env.local (chemin vers composer ou composer.phar).");
    }

    /**
     * @param list<string> $command
     */
    private function exec(Deployment $deployment, array $command, int $timeout): void
    {
        $this->log($deployment, '$ '.implode(' ', array_map(static fn (string $part) => str_contains($part, ' ') ? '"'.$part.'"' : $part, array_slice($command, 0, 6))));

        $env = [
            'COMPOSER_NO_INTERACTION' => '1',
            'COMPOSER_HOME'           => getenv('COMPOSER_HOME') ?: $this->projectDir.'/var/composer',
            'GIT_TERMINAL_PROMPT'     => '0',
        ];
        if (!getenv('HOME') && !getenv('USERPROFILE')) {
            $env['HOME'] = $this->projectDir.'/var';
        }

        $process = new Process($command, $this->projectDir, $env, null, $timeout);
        $process->run(function (string $type, string $buffer) use ($deployment): void {
            $clean = trim(preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $buffer) ?? '');
            if ($clean !== '') {
                $this->log($deployment, $clean);
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('la commande a échoué (code %d).', $process->getExitCode()));
        }
    }

    private function step(Deployment $deployment, string $step): void
    {
        $deployment->setStep($step);
        $this->log($deployment, "\n▸ {$step}");
    }

    private function log(Deployment $deployment, string $line): void
    {
        $deployment->appendLog($line."\n");
        $this->em->flush();
    }

    private function finish(Deployment $deployment, string $status): void
    {
        $deployment->setStatus($status)->setFinishedAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}

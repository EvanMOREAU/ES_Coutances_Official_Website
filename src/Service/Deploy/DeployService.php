<?php

namespace App\Service\Deploy;

use App\Entity\Deployment;
use App\Entity\User;
use App\Repository\DeploymentRepository;
use App\Service\Backup\DatabaseBackup;
use App\Service\Backup\SiteFilesBackup;
use App\Service\ProductionChecklist;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Mise à jour du site depuis le dépôt Git (GitHub) en un clic.
 *
 * La mise à jour est lancée dans un processus indépendant (`app:deploy <id>`),
 * pour ne pas dépendre de la durée de la requête web et survivre au vidage du
 * cache. Sa progression est écrite dans l'entité Deployment, que l'interface
 * relit toutes les secondes.
 *
 * Étapes : récupération du code → SAUVEGARDE de la base puis des fichiers du site →
 * application du code (fast-forward uniquement, jamais de fusion automatique) →
 * dépendances Composer → migrations → styles → cache.
 *
 * Si une étape échoue après que le code a changé, le site est remis automatiquement dans son
 * état d'avant (code, dépendances et — si les migrations avaient commencé — base de données).
 * La sauvegarde de la base n'est jamais supprimée ; celle des fichiers l'est seulement sur
 * validation de l'utilisateur (purgeFilesBackup).
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
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
        private readonly ProductionChecklist $checklist,
        private readonly DatabaseBackup $dbBackup,
        private readonly SiteFilesBackup $siteBackup,
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

    /**
     * Commits en attente d'après les références déjà connues (sans contacter GitHub).
     *
     * @return list<array{hash: string, short: string, author: string, date: \DateTimeImmutable, subject: string}>
     */
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

        $this->spawnConsole('app:deploy', [(string) $deployment->getId()]);

        return $deployment;
    }

    /**
     * Lance une commande de la console dans un processus détaché (la requête web n'attend pas sa fin).
     *
     * @param list<string> $arguments
     */
    public function spawnConsole(string $command, array $arguments = []): void
    {
        $line = sprintf(
            '%s %s %s%s --env=%s',
            escapeshellarg($this->phpBinary()),
            escapeshellarg($this->projectDir.'/bin/console'),
            $command,
            implode('', array_map(static fn (string $argument) => ' '.escapeshellarg($argument), $arguments)),
            escapeshellarg($this->kernelEnvironment),
        );

        $shell = \PHP_OS_FAMILY === 'Windows'
            ? sprintf('start /B "" %s > NUL 2>&1', $line)
            : sprintf('nohup %s > /dev/null 2>&1 < /dev/null &', $line);

        $process = Process::fromShellCommandline($shell, $this->projectDir, null, null, 20);
        $process->run();
    }

    /**
     * Supprime l'archive des fichiers d'une mise à jour, une fois l'utilisateur sûr que le site fonctionne.
     * La sauvegarde de la base de données, elle, n'est jamais supprimée.
     *
     * @throws \RuntimeException
     */
    public function purgeFilesBackup(Deployment $deployment): void
    {
        if ($deployment->isRunning()) {
            throw new \RuntimeException('Cette mise à jour est encore en cours.');
        }
        if (!$deployment->hasFilesBackup()) {
            throw new \RuntimeException('Il n\'y a pas (ou plus) de sauvegarde des fichiers pour cette mise à jour.');
        }

        $this->siteBackup->delete((string) $deployment->getFilesBackup());
        $deployment->setFilesBackupDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    // -- Exécution (dans le processus `app:deploy`) -----------------------------

    /**
     * Enchaîne les étapes. Chaque sortie est ajoutée au journal du déploiement.
     */
    public function run(Deployment $deployment): bool
    {
        $php     = $this->phpBinary();
        $console = [$php, $this->projectDir.'/bin/console', '--no-interaction', '--env='.$this->kernelEnvironment];
        $isProd  = $this->kernelEnvironment === 'prod';

        $from        = null;
        $codeChanged = false; // le code du site a été remplacé : un échec déclenche le retour arrière
        $dataTouched = false; // les migrations ont commencé : la base peut avoir changé, elle sera restaurée aussi

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

            // Rien n'est modifié tant que les deux sauvegardes ne sont pas terminées.
            $label = $this->git->shortHash($from);

            $this->step($deployment, 'Sauvegarde de la base de données');
            $database = $this->dbBackup->create(DatabaseBackup::KIND_UPDATE, $label);
            $deployment->setDbBackup($database['file']);
            $this->log($deployment, sprintf('✓ %s (%s) — conservée définitivement, téléchargeable depuis Développeur > Sauvegardes.', $database['file'], $this->size($database['size'])));

            $this->step($deployment, 'Sauvegarde des fichiers du site');
            $files = $this->siteBackup->create($label);
            $deployment->setFilesBackup($files['file'])->setFilesBackupSize($files['size']);
            $this->log($deployment, sprintf('✓ %s (%d fichiers, %s) — à supprimer depuis l\'historique une fois la mise à jour validée.', $files['file'], $files['files'], $this->size($files['size'])));

            $this->step($deployment, 'Application du code');
            $codeChanged = true;
            $this->git->fastForwardTo($branch);
            $deployment->setToCommit($this->git->head());
            $this->log($deployment, 'Code à jour : '.$this->git->shortHash($deployment->getToCommit()).'.');

            $this->step($deployment, 'Dépendances PHP');
            $composer = $this->composerCommand();
            $this->exec($deployment, [...$composer, 'install', '--no-interaction', '--no-progress', ...($isProd ? ['--no-dev', '--optimize-autoloader'] : [])], 900);

            $this->step($deployment, 'Migrations de la base de données');
            $dataTouched = true;
            $this->exec($deployment, [...$console, 'doctrine:migrations:migrate', '--allow-no-migration'], 600);

            $this->step($deployment, 'Compilation des styles');
            $this->exec($deployment, [...$console, 'tailwind:build', ...($isProd ? ['--minify'] : [])], 300);

            if ($isProd) {
                $this->step($deployment, 'Compilation des assets');
                $this->exec($deployment, [...$console, 'asset-map:compile'], 300);
            }

            $this->step($deployment, 'Vidage du cache');
            $this->exec($deployment, [...$console, 'cache:clear'], 300);

            if ($isProd) {
                $this->step($deployment, 'Contrôle de la configuration');
                foreach ($this->checklist->run() as $check) {
                    $this->log($deployment, sprintf('  %s %s%s', $check['ok'] ? '✓' : '⚠', $check['label'], $check['ok'] ? '' : ' — '.$check['advice']));
                }
            }

            $this->step($deployment, 'Terminé');
            $this->log($deployment, "\n✓ Mise à jour déployée avec succès.");
            $this->log($deployment, 'Vérifiez le site, puis supprimez la sauvegarde des fichiers depuis l\'historique (la sauvegarde de la base est conservée).');
            $this->finish($deployment, Deployment::STATUS_SUCCESS);

            return true;
        } catch (\Throwable $e) {
            $this->safeLog($deployment, "\n✗ Échec à l'étape « ".$deployment->getStep().' » : '.$e->getMessage());

            if ($codeChanged) {
                $deployment = $this->rollback($deployment, $from, $dataTouched, $console, $isProd);
            } else {
                $this->safeLog($deployment, 'Aucune modification n\'a été faite : le site est resté dans son état d\'origine.');
            }
            $this->finish($deployment, Deployment::STATUS_FAILED);

            return false;
        }
    }

    /**
     * Remet le site dans son état d'avant la mise à jour : base de données (seulement si les migrations
     * avaient commencé, pour ne pas écraser inutilement les données saisies entre-temps), code Git, puis
     * fichiers et dépendances depuis l'archive. Ne lève jamais d'exception : l'issue est écrite dans le journal.
     *
     * @param list<string> $console
     *
     * @return Deployment le déploiement à terminer (rechargé si la restauration de la base l'a remplacé)
     */
    private function rollback(Deployment $deployment, ?string $from, bool $restoreDatabase, array $console, bool $isProd): Deployment
    {
        try {
            $this->step($deployment, 'Retour arrière automatique');
            $this->log($deployment, 'La mise à jour a échoué : remise du site dans son état d\'avant.');

            if ($restoreDatabase) {
                $file = (string) $deployment->getDbBackup();
                $path = $this->dbBackup->path($file) ?? throw new \RuntimeException("Sauvegarde de la base introuvable : $file");
                $this->log($deployment, "Restauration de la base de données ($file)…");
                $this->dbBackup->restore($path);
                // La table des déploiements vient d'être remplacée par son contenu d'avant : on y réécrit celui-ci.
                $deployment = $this->reattach($deployment);
                $this->log($deployment, '✓ Base de données restaurée.');
            } else {
                $this->log($deployment, 'La base de données n\'avait pas encore été modifiée : aucune restauration nécessaire.');
            }

            if ($from !== null) {
                $this->git->resetHard($from);
                $this->log($deployment, '✓ Code remis au commit '.$this->git->shortHash($from).'.');
            }
            $archive  = (string) $deployment->getFilesBackup();
            $restored = $this->siteBackup->restore($archive);
            $this->log($deployment, sprintf('✓ %d fichiers du site restaurés depuis %s (dépendances comprises).', $restored, $archive));

            $this->step($deployment, 'Reconstruction du site');
            $rebuild = [['Styles', [...$console, 'tailwind:build', ...($isProd ? ['--minify'] : [])], 300]];
            if ($isProd) {
                $rebuild[] = ['Assets', [...$console, 'asset-map:compile'], 300];
            }
            $rebuild[] = ['Cache', [...$console, 'cache:clear'], 300];
            foreach ($rebuild as [$label, $command, $timeout]) {
                try {
                    $this->exec($deployment, $command, $timeout);
                } catch (\Throwable $e) {
                    $this->log($deployment, "⚠ $label : ".$e->getMessage());
                }
            }

            $deployment->setRolledBack(true);
            $this->log($deployment, "\n✓ Retour arrière terminé : le site fonctionne de nouveau avec la version précédente.");
            $this->log($deployment, 'Les deux sauvegardes sont conservées (la sauvegarde des fichiers peut être supprimée depuis l\'historique).');
        } catch (\Throwable $e) {
            $this->logger->critical('Retour arrière automatique impossible : {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $this->safeLog($deployment, sprintf(
                "\n✗ Retour arrière automatique IMPOSSIBLE : %s\nLe site est peut-être dans un état incohérent. Les sauvegardes sont intactes :\n  · base : var/backups/db/%s (commande : php bin/console app:db:restaurer %s)\n  · fichiers : var/backups/site/%s\n  · code : git reset --hard %s",
                $e->getMessage(),
                $deployment->getDbBackup() ?? '?',
                $deployment->getDbBackup() ?? '?',
                $deployment->getFilesBackup() ?? '?',
                $from ?? '?',
            ));
        }

        return $deployment;
    }

    /**
     * Après une restauration de la base, retrouve (ou recrée) la ligne du déploiement et y reporte l'état
     * en mémoire : celui de la sauvegarde est antérieur à la fin de la mise à jour.
     */
    private function reattach(Deployment $current): Deployment
    {
        $id = $current->getId();
        $this->em->clear();

        $fresh = null !== $id ? $this->deployments->find($id) : null;
        if (!$fresh instanceof Deployment) {
            $fresh = new Deployment();
            $this->em->persist($fresh);
        }
        $fresh->restoreFrom($current);
        $this->em->flush();

        return $fresh;
    }

    /** Écrit dans le journal sans jamais lever d'exception (base de données éventuellement indisponible). */
    private function safeLog(Deployment $deployment, string $line): void
    {
        try {
            $this->log($deployment, $line);
        } catch (\Throwable $e) {
            $this->logger->error('Journal de déploiement non enregistré : {message} — {line}', ['message' => $e->getMessage(), 'line' => $line]);
        }
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' Mo' : max(1, (int) round($bytes / 1024)).' Ko';
    }

    /** @return list<string> */
    private function composerCommand(): array
    {
        $configured = $_SERVER['COMPOSER_BIN'] ?? $_ENV['COMPOSER_BIN'] ?? null;
        $finder     = new ExecutableFinder();

        foreach (array_filter([$configured, 'composer', 'composer.phar']) as $candidate) {
            $path = is_file((string) $candidate) ? $candidate : $finder->find((string) $candidate);
            if ($path !== null) {
                // Toujours lancé explicitement avec le PHP du site : un composer
                // système (script avec shebang #!/usr/bin/env php) peut sinon
                // s'exécuter avec une autre version de PHP que celle requise.
                return [$this->phpBinary(), $path];
            }
        }

        throw new \RuntimeException('Composer est introuvable : définissez COMPOSER_BIN dans .env.local (chemin vers composer ou composer.phar).');
    }

    /**
     * Binaire PHP à utiliser pour les sous-processus (console, composer).
     *
     * PhpExecutableFinder ne trouve pas de manière fiable le CLI depuis un
     * processus PHP-FPM (le déploiement est lancé depuis une requête web) et
     * retombe alors sur le simple `php` du PATH, qui peut être une version
     * différente de celle qui fait tourner le site (ex. 8.2 vs 8.4 sur un
     * serveur avec plusieurs PHP installés) : le sous-processus échoue alors
     * silencieusement (sortie jetée par le `nohup ... > /dev/null`), et le
     * déploiement reste bloqué sur « Démarrage » sans aucune erreur visible.
     * On force donc explicitement la version majeure.mineure de PHP courante.
     */
    private function phpBinary(): string
    {
        $configured = $_SERVER['PHP_CLI_BIN'] ?? $_ENV['PHP_CLI_BIN'] ?? null;
        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        $versioned = 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $found     = (new ExecutableFinder())->find($versioned);
        if ($found !== null) {
            return $found;
        }

        return (new PhpExecutableFinder())->find(false) ?: 'php';
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

        $this->notify($deployment);
    }

    /**
     * Prévient par e-mail tous les comptes développeur de l'issue de chaque déploiement : un
     * déploiement lancé à l'insu de l'équipe (compte compromis) ou raté ne passe pas inaperçu.
     * Un échec d'envoi ne doit jamais faire échouer le déploiement.
     */
    private function notify(Deployment $deployment): void
    {
        try {
            $recipients = array_filter(array_map(
                static fn (User $user): ?string => $user->getEmail(),
                $this->em->getRepository(User::class)->createQueryBuilder('u')
                    ->where('u.roles LIKE :dev')->setParameter('dev', '%ROLE_DEV%')
                    ->getQuery()->getResult(),
            ));
            if ([] === $recipients) {
                return;
            }

            $success = Deployment::STATUS_SUCCESS === $deployment->getStatus();
            $short   = static fn (?string $hash): string => null === $hash ? '—' : substr($hash, 0, 7);
            $body    = sprintf(
                "Mise à jour n°%d : %s

Lancée par : %s
Début : %s
Commit : %s → %s
%s
Journal complet : Administration > Développeur > Mise à jour.

Si vous n'êtes pas à l'origine de cette mise à jour, changez immédiatement les mots de passe des comptes développeur.",
                $deployment->getId(),
                $success ? 'réussi' : 'ÉCHEC',
                $deployment->getTriggeredBy() ?? 'inconnu',
                $deployment->getStartedAt()->format('d/m/Y H:i'),
                $short($deployment->getFromCommit()),
                $short($deployment->getToCommit()),
                match (true) {
                    $success => $deployment->getDbBackup() ? sprintf("Sauvegarde de la base : %s (conservée).\nSauvegarde des fichiers : à supprimer depuis l'historique une fois la mise à jour validée.\n", $deployment->getDbBackup()) : '',
                    $deployment->isRolledBack() => "Le site a été remis automatiquement dans son état d'avant la mise à jour.\n",
                    default => "Le retour arrière automatique n'a pas abouti ou n'était pas nécessaire : consultez le journal.\n",
                },
            );

            $this->mailer->send((new Email())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(...$recipients)
                ->subject(sprintf('[ES Coutances] Mise à jour %s', $success ? 'réussie' : 'en échec'))
                ->text($body));
        } catch (\Throwable $e) {
            $this->logger->warning('Notification de mise à jour non envoyée : {message}', ['message' => $e->getMessage()]);
        }
    }
}

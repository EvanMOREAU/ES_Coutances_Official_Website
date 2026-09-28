<?php

namespace App\Service\Deploy;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Petit adaptateur autour du binaire `git` pour le dépôt du site.
 *
 * Toutes les commandes tournent dans le dossier du projet, sans jamais
 * demander d'identifiants à l'écran (GIT_TERMINAL_PROMPT=0) : si le dépôt
 * distant exige une authentification que le serveur ne possède pas, la
 * commande échoue proprement au lieu de rester bloquée.
 */
class GitRepository
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $deployBranch,
    ) {
    }

    public function isAvailable(): bool
    {
        return (new ExecutableFinder())->find('git') !== null && is_dir($this->projectDir.'/.git');
    }

    /**
     * @param list<string> $args
     *
     * @throws \RuntimeException si la commande échoue
     */
    public function run(array $args, int $timeout = 120): string
    {
        $git = (new ExecutableFinder())->find('git');
        if ($git === null) {
            throw new \RuntimeException("Git n'est pas installé sur ce serveur.");
        }

        $process = new Process([$git, ...$args], $this->projectDir, ['GIT_TERMINAL_PROMPT' => '0'], null, $timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new \RuntimeException($message !== '' ? $message : 'git '.$args[0].' a échoué.');
        }

        return trim($process->getOutput());
    }

    public function head(): ?string
    {
        try {
            return $this->run(['rev-parse', 'HEAD'], 15);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** Branche à suivre : DEPLOY_BRANCH si définie, sinon la branche actuellement extraite. */
    public function branch(): string
    {
        if ($this->deployBranch !== '') {
            return $this->deployBranch;
        }

        $current = $this->run(['rev-parse', '--abbrev-ref', 'HEAD'], 15);
        if ($current === 'HEAD') {
            throw new \RuntimeException("Le dépôt est en « HEAD détaché » : définissez DEPLOY_BRANCH dans .env.local.");
        }

        return $current;
    }

    public function isDirty(): bool
    {
        return $this->run(['status', '--porcelain', '--untracked-files=no'], 30) !== '';
    }

    public function fetch(string $branch): void
    {
        try {
            $this->run(['fetch', '--quiet', 'origin', $branch], 120);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), "couldn't find remote ref")) {
                throw new \RuntimeException(sprintf("La branche « %s » n'existe pas sur GitHub (origin) : envoyez-la d'abord avec « git push ».", $branch), 0, $e);
            }

            throw $e;
        }
    }

    /**
     * Commits présents sur origin/<branche> mais pas encore déployés (du plus récent au plus ancien).
     *
     * @return list<array{hash: string, short: string, author: string, date: \DateTimeImmutable, subject: string}>
     */
    public function pending(string $branch, ?string $from = null): array
    {
        return $this->log(($from ?? 'HEAD').'..origin/'.$branch);
    }

    /**
     * @return list<array{hash: string, short: string, author: string, date: \DateTimeImmutable, subject: string}>
     */
    public function log(string $range, int $max = 200): array
    {
        $output = $this->run(['log', $range, '--no-merges', '--max-count='.$max, '--format=%H%x1f%an%x1f%aI%x1f%s'], 30);
        if ($output === '') {
            return [];
        }

        $commits = [];
        foreach (explode("\n", $output) as $line) {
            [$hash, $author, $date, $subject] = array_pad(explode("\x1f", $line, 4), 4, '');
            $commits[] = [
                'hash'    => $hash,
                'short'   => substr($hash, 0, 7),
                'author'  => $author,
                'date'    => new \DateTimeImmutable($date),
                'subject' => $subject,
            ];
        }

        return $commits;
    }

    /** @return array{hash: string, short: string, author: string, date: \DateTimeImmutable, subject: string}|null */
    public function currentCommit(): ?array
    {
        try {
            return $this->log('HEAD', 1)[0] ?? null;
        } catch (\RuntimeException) {
            return null;
        }
    }

    public function fastForwardTo(string $branch): void
    {
        $this->run(['merge', '--ff-only', 'origin/'.$branch], 120);
    }

    public function shortHash(?string $hash): string
    {
        return $hash !== null ? substr($hash, 0, 7) : '—';
    }
}

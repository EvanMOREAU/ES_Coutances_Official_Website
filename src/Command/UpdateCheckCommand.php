<?php

namespace App\Command;

use App\Service\Deploy\DeployService;
use App\Service\Deploy\UpdateWatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interroge GitHub pour savoir si une mise à jour est disponible (le résultat alimente la pastille du menu).
 * Lancée en arrière-plan par le site lui-même ; peut aussi l'être par le cron (deploy/crontab.example).
 */
#[AsCommand(name: 'app:update:verifier', description: 'Vérifie auprès de GitHub si une mise à jour du site est disponible')]
class UpdateCheckCommand extends Command
{
    public function __construct(
        private readonly UpdateWatcher $watcher,
        private readonly DeployService $deploy,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null !== $this->deploy->running()) {
            $output->writeln('Une mise à jour est en cours : vérification ignorée.');

            return Command::SUCCESS;
        }

        try {
            $count = $this->watcher->fetchNow();
        } catch (\RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(0 === $count ? 'Le site est à jour.' : sprintf('%d mise(s) à jour disponible(s).', $count));

        return Command::SUCCESS;
    }
}

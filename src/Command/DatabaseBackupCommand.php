<?php

namespace App\Command;

use App\Service\Backup\DatabaseBackup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sauvegarde automatique de la base de données (tâche planifiée : deploy/crontab.example).
 * Seules les sauvegardes « automatiques » les plus anciennes sont purgées ; celles prises avant une
 * mise à jour ou à la demande depuis l'administration ne sont jamais supprimées.
 */
#[AsCommand(name: 'app:db:sauvegarder', description: 'Sauvegarde la base de données dans var/backups/db')]
class DatabaseBackupCommand extends Command
{
    public function __construct(private readonly DatabaseBackup $backup)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('conserver', null, InputOption::VALUE_REQUIRED, 'Nombre de sauvegardes automatiques à garder (0 = toutes)', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->backup->create(DatabaseBackup::KIND_AUTO);
        } catch (\Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
        $output->writeln(sprintf('Sauvegarde : %s (%d Ko)', $result['file'], max(1, (int) round($result['size'] / 1024))));

        $keep = (int) $input->getOption('conserver');
        if ($keep > 0 && ($removed = $this->backup->pruneAutomatic($keep)) > 0) {
            $output->writeln(sprintf('%d ancienne(s) sauvegarde(s) automatique(s) supprimée(s) (les %d plus récentes sont gardées).', $removed, $keep));
        }

        return Command::SUCCESS;
    }
}

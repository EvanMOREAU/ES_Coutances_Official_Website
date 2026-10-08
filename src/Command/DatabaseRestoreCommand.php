<?php

namespace App\Command;

use App\Service\Backup\DatabaseBackup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Restauration manuelle d'une sauvegarde, en ligne de commande uniquement : le site n'offre volontairement
 * aucun moyen de remplacer la base depuis l'interface (seul le retour arrière d'une mise à jour échouée le fait).
 */
#[AsCommand(name: 'app:db:restaurer', description: 'Remplace la base de données par une sauvegarde de var/backups/db (opération destructive)')]
class DatabaseRestoreCommand extends Command
{
    public function __construct(private readonly DatabaseBackup $backup)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Nom du fichier de var/backups/db (voir Développeur > Sauvegardes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('fichier');
        $path = $this->backup->path(basename($file));
        if (null === $path) {
            $io->error('Sauvegarde introuvable : '.$file);

            return Command::FAILURE;
        }

        if (!$io->confirm(sprintf('Toutes les données actuelles seront REMPLACÉES par celles de « %s ». Continuer ?', basename($path)), false)) {
            $io->warning('Annulé.');

            return Command::SUCCESS;
        }

        try {
            $this->backup->restore($path);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success('Base de données restaurée. Pensez à vider le cache (php bin/console cache:clear).');

        return Command::SUCCESS;
    }
}

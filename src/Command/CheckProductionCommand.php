<?php

namespace App\Command;

use App\Service\ProductionChecklist;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:securite:verifier', description: 'Contrôle la configuration de production (debug, clé secrète, double authentification…)')]
class CheckProductionCommand extends Command
{
    public function __construct(private readonly ProductionChecklist $checklist)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $checks = $this->checklist->run();

        $io->table(['Contrôle', 'Résultat'], array_map(
            static fn (array $c): array => [$c['label'], $c['ok'] ? '<info>OK</info>' : ($c['blocking'] ? '<error> ÉCHEC </error>' : '<comment>À voir</comment>')],
            $checks,
        ));
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $io->writeln(sprintf('  • %s — %s', $check['label'], $check['advice']));
            }
        }

        if (ProductionChecklist::hasBlockingFailure($checks)) {
            $io->error('Configuration à corriger avant de considérer le site comme sécurisé.');

            return Command::FAILURE;
        }
        $io->success('Configuration de production conforme.');

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Service\SaisonCloture;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:saison:cloturer', description: 'Archive les licenciés dont la saison est terminée (à planifier chaque jour).')]
class CloturerSaisonCommand extends Command
{
    public function __construct(private readonly SaisonCloture $cloture)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->success(sprintf('%d licencié(s) archivé(s).', $this->cloture->cloturer()));

        return Command::SUCCESS;
    }
}

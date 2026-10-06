<?php

namespace App\Command;

use App\Service\Chat\ChatService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:messagerie:purger-documents', description: 'Supprime les documents de la messagerie arrivés à échéance (14 jours)')]
class PurgeChatDocumentsCommand extends Command
{
    public function __construct(private readonly ChatService $chat)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->chat->purgeExpired();
        (new SymfonyStyle($input, $output))->success(sprintf('%d document(s) arrivé(s) à échéance traité(s).', $count));

        return Command::SUCCESS;
    }
}

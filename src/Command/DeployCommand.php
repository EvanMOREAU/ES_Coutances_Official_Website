<?php

namespace App\Command;

use App\Entity\Deployment;
use App\Repository\DeploymentRepository;
use App\Service\Deploy\DeployService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exécute un déploiement créé depuis l'admin (Développeur → Déploiement).
 * Lancé en arrière-plan par DeployService::start() ; peut aussi l'être à la main.
 */
#[AsCommand(name: 'app:deploy', description: "Met le site à jour depuis le dépôt Git (étapes du déploiement enregistré sous l'identifiant donné)")]
class DeployCommand extends Command
{
    public function __construct(
        private readonly DeployService $deploy,
        private readonly DeploymentRepository $deployments,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Identifiant du déploiement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deployment = $this->deployments->find((int) $input->getArgument('id'));
        if (!$deployment instanceof Deployment || !$deployment->isRunning()) {
            $output->writeln('<error>Déploiement introuvable ou déjà terminé.</error>');

            return Command::FAILURE;
        }

        return $this->deploy->run($deployment) ? Command::SUCCESS : Command::FAILURE;
    }
}

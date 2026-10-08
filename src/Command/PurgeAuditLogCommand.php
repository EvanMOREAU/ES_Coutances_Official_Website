<?php

namespace App\Command;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime du journal d'activité les lignes plus anciennes que la durée de conservation annoncée
 * dans la politique de confidentialité (12 mois). À planifier chaque nuit (voir README).
 */
#[AsCommand(name: 'app:audit:purger', description: "Supprime les entrées du journal d'activité plus anciennes que la durée de conservation (12 mois par défaut)")]
class PurgeAuditLogCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('mois', null, InputOption::VALUE_REQUIRED, 'Durée de conservation en mois', '12');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $months = max(1, (int) $input->getOption('mois'));
        $limit  = (new \DateTimeImmutable())->modify(sprintf('-%d months', $months));

        // Requête DQL directe : pas de passage par l'écouteur d'audit (qui consignerait la suppression elle-même).
        $deleted = $this->em->createQuery(sprintf('DELETE FROM %s a WHERE a.occurredAt < :limit', AuditLog::class))
            ->setParameter('limit', $limit)
            ->execute();

        $io->success(sprintf('%d entrée(s) du journal antérieure(s) au %s supprimée(s).', $deleted, $limit->format('d/m/Y')));

        return Command::SUCCESS;
    }
}

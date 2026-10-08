<?php

namespace App\Command;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Service\Privacy\AccountDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Anonymise les comptes (hors équipe) sans connexion depuis plus de 36 mois, durée annoncée dans la
 * politique de confidentialité. Un compte rattaché à un licencié encore en cours (saison non
 * terminée) n'est jamais touché. Les pièces comptables sont conservées sous forme anonymisée.
 */
#[AsCommand(name: 'app:rgpd:purger-inactifs', description: 'Anonymise les comptes inactifs depuis plus de 36 mois (durée de conservation annoncée)')]
class PurgeInactiveAccountsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountDataService $privacy,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mois', null, InputOption::VALUE_REQUIRED, "Durée d'inactivité en mois", '36')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les comptes concernés sans rien modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $months = max(12, (int) $input->getOption('mois'));
        $limit  = (new \DateTimeImmutable())->modify(sprintf('-%d months', $months));
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var list<User> $candidates */
        $candidates = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.anonymizedAt IS NULL')
            ->andWhere('u.lastLoginAt IS NOT NULL AND u.lastLoginAt < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()->getResult();

        $done = 0;
        foreach ($candidates as $user) {
            if (!$this->privacy->canAnonymize($user) || $this->hasActiveLicence($user)) {
                continue;
            }
            if ($dryRun) {
                $io->writeln(sprintf('  • %s (dernière connexion : %s)', $user->getEmail(), $user->getLastLoginAt()?->format('d/m/Y')));
            } else {
                $this->privacy->anonymize($user);
            }
            ++$done;
        }

        $io->success(sprintf('%d compte(s) %s (inactifs depuis le %s).', $done, $dryRun ? 'à anonymiser' : 'anonymisé(s)', $limit->format('d/m/Y')));

        return Command::SUCCESS;
    }

    private function hasActiveLicence(User $user): bool
    {
        foreach ($this->em->getRepository(Licencie::class)->findBy(['user' => $user]) as $licencie) {
            if ($licencie->isEnCours()) {
                return true;
            }
        }
        foreach ($this->em->getRepository(Famille::class)->findBy(['user' => $user]) as $famille) {
            foreach ($famille->getLicencies() as $licencie) {
                if ($licencie->isEnCours()) {
                    return true;
                }
            }
        }

        return false;
    }
}

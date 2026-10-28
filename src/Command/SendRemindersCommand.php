<?php

namespace App\Command;

use App\Repository\EntrainementRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rappel par e-mail, la veille, des entraînements, rencontres et événements du planning (rubrique « Rappels la veille »
 * des préférences de notification). À lancer une fois par jour en fin d'après-midi (voir deploy/crontab.example) :
 * relancée le même jour, elle réenverrait les mêmes rappels.
 */
#[AsCommand(name: 'app:rappels:envoyer', description: 'Envoie les rappels de la veille (entraînements, rencontres, événements)')]
final class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly EntrainementRepository $events,
        private readonly UserRepository $users,
        private readonly NotificationMailer $notifications,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Jour des événements à rappeler (par défaut : demain)', 'tomorrow');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $day = new \DateTimeImmutable((string) $input->getOption('date'));
        } catch (\Exception) {
            $io->error('Date invalide.');

            return Command::INVALID;
        }

        $events = $this->events->between($day->setTime(0, 0), $day->setTime(23, 59, 59));
        $users  = $this->users->findAll();
        $sent   = 0;
        foreach ($events as $event) {
            $sent += $this->notifications->notifyEvent($event, $users, 'rappels', 'notif.mail.title.reminder');
        }

        $io->success(sprintf('%d rappel(s) envoyé(s) pour %d événement(s) du %s.', $sent, \count($events), $day->format('d/m/Y')));

        return Command::SUCCESS;
    }
}

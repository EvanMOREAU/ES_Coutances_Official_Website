<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Crée (ou remet d'aplomb) un compte administrateur ; sans danger si on le relance. */
#[AsCommand(name: 'app:ensure-admin', description: 'Crée ou met à jour un compte administrateur (idempotent)')]
class EnsureAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'E-mail du compte', 'admin@escoutances.fr')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe', '1234')
            ->addArgument('nom', InputArgument::OPTIONAL, 'Nom complet', 'Administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user    = $this->users->findOneBy(['email' => $email]);
        $created = null === $user;
        if ($created) {
            $user = (new User())->setEmail($email)->setNom((string) $input->getArgument('nom'));
        }
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, (string) $input->getArgument('password')));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Compte %s %s (ROLE_ADMIN).', $email, $created ? 'créé' : 'mis à jour'));
        if ('1234' === $input->getArgument('password')) {
            $io->warning('Mot de passe faible : à changer avant toute mise en production.');
        }

        return Command::SUCCESS;
    }
}

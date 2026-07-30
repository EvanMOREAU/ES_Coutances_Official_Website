<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Crée un utilisateur admin ou éditeur')]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email',    InputArgument::REQUIRED, 'Email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addArgument('nom',      InputArgument::REQUIRED, 'Nom complet')
            ->addArgument('role',     InputArgument::OPTIONAL, 'ROLE_ADMIN ou ROLE_EDITOR', 'ROLE_EDITOR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = new User();
        $user->setEmail($input->getArgument('email'));
        $user->setNom($input->getArgument('nom'));
        $user->setRoles([$input->getArgument('role')]);
        $user->setPassword($this->hasher->hashPassword($user, $input->getArgument('password')));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Utilisateur %s créé avec le rôle %s', $user->getEmail(), $input->getArgument('role')));

        return Command::SUCCESS;
    }
}
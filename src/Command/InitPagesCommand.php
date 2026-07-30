<?php

namespace App\Command;

use App\Entity\PageContenu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:init-pages', description: 'Initialise les pages de contenu')]
class InitPagesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pages = [
            [
                'slug'    => 'histoire',
                'titre'   => 'Histoire du club',
                'contenu' => '<p>Depuis 1961, l\'Entente Sportive Coutançaise forme des joueurs, des éducateurs et des passionnés de football en Normandie.</p><p>Modifiez cette page depuis le panel d\'administration.</p>',
            ],
            [
                'slug'    => 'infrastructure',
                'titre'   => 'Infrastructure',
                'contenu' => '<p>Découvrez les installations sportives de l\'ES Coutances.</p><p>Modifiez cette page depuis le panel d\'administration.</p>',
            ],
        ];

        foreach ($pages as $data) {
            $existing = $this->em->getRepository(PageContenu::class)->findOneBy(['slug' => $data['slug']]);
            if ($existing) {
                $io->warning('Page "' . $data['slug'] . '" déjà existante, ignorée.');
                continue;
            }
            $page = new PageContenu();
            $page->setSlug($data['slug']);
            $page->setTitre($data['titre']);
            $page->setContenu($data['contenu']);
            $this->em->persist($page);
            $io->success('Page "' . $data['slug'] . '" créée.');
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}
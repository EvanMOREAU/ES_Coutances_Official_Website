<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\ChangelogFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Journal des modifications, lu depuis changelog/releases.yaml. */
#[Route('/admin/changelog', name: 'admin_changelog_index', methods: ['GET'])]
class ChangelogController extends AbstractController
{
    public function __invoke(ChangelogFile $changelog, EntityManagerInterface $em): Response
    {
        $releases = $changelog->releases();
        $latest   = $releases[0] ?? null;

        // Consulter la page marque la dernière version comme lue pour cet utilisateur.
        $user = $this->getUser();
        if ($user instanceof User && $latest && $user->getChangelogVersionVue() !== $latest['version']) {
            $user->setChangelogVersionVue($latest['version']);
            $em->flush();
        }

        return $this->render('admin/changelog/index.html.twig', [
            'releases' => $releases,
            'changes'  => array_sum(array_column($releases, 'count')),
            'latest'   => $latest,
            'types'    => ChangelogFile::TYPES,
        ]);
    }
}

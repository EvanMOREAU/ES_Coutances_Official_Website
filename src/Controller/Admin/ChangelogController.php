<?php

namespace App\Controller\Admin;

use App\Service\ChangelogFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Journal des modifications, lu depuis changelog/releases.yaml. */
#[Route('/admin/changelog', name: 'admin_changelog_index', methods: ['GET'])]
class ChangelogController extends AbstractController
{
    public function __invoke(ChangelogFile $changelog): Response
    {
        $releases = $changelog->releases();

        return $this->render('admin/changelog/index.html.twig', [
            'releases' => $releases,
            'changes'  => array_sum(array_column($releases, 'count')),
            'latest'   => $releases[0] ?? null,
            'types'    => ChangelogFile::TYPES,
        ]);
    }
}

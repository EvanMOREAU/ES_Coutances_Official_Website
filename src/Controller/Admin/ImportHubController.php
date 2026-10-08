<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page « Import » de l'administration : une carte par source d'import (pour
 * l'instant Foot Club), qui ouvre l'outil dans une grande fenêtre comme les
 * pages « Gestion des licenciés » et « Site vitrine ».
 */
#[Route('/admin/imports', name: 'admin_import_hub', methods: ['GET'])]
class ImportHubController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('admin/import_hub.html.twig', [
            'open'     => '',
            'openView' => 'list',
            'sections' => [[
                'key'    => 'import',
                'group'  => 'Imports',
                'icon'   => 'fa-file-import',
                'title'  => 'Import Foot Club',
                'url'    => $this->generateUrl('admin_import_index'),
                'new'    => null,
                'value'  => null,
                'label'  => null,
                'sub'    => null,
                'visual' => [
                    'type'    => 'status',
                    'on'      => true,
                    'onLabel' => 'Familles et licenciés',
                    'offLabel' => '',
                    'detail'  => 'Importe l\'export du logiciel Foot Club : crée ou met à jour les familles et les licenciés, et renouvelle la saison des licences valides.',
                ],
            ]],
        ]);
    }
}

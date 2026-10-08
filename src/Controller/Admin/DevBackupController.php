<?php

namespace App\Controller\Admin;

use App\Service\Backup\DatabaseBackup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sauvegardes de la base de données : extraction et téléchargement.
 *
 * Réservé au rôle développeur, et à lui seul : aucune autorisation (profil, ajout individuel) ne peut
 * l'ouvrir à un autre compte — le contrôle porte sur le rôle, pas sur le catalogue d'autorisations
 * (voir RoutePermissions::DEV_PREFIXES). Les sauvegardes contiennent toutes les données personnelles du site.
 * Il n'existe volontairement AUCUNE action de restauration ni de suppression depuis l'interface.
 */
#[Route('/admin/developpeur/sauvegardes')]
#[IsGranted('ROLE_DEV')]
class DevBackupController extends AbstractController
{
    public function __construct(private readonly DatabaseBackup $backup)
    {
    }

    #[Route('', name: 'admin_dev_backup_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/dev/backup.html.twig', [
            'backups' => $this->backup->all(),
            'kinds'   => DatabaseBackup::KINDS,
        ]);
    }

    /** Extrait la base maintenant (copie « manuelle », jamais purgée automatiquement). */
    #[Route('/creer', name: 'admin_dev_backup_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('dev-backup', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $result = $this->backup->create(DatabaseBackup::KIND_MANUAL);
            $this->addFlash('success', sprintf('Sauvegarde créée : %s.', $result['file']));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'La sauvegarde a échoué : '.$e->getMessage());
        }

        return $this->redirectToRoute('admin_dev_backup_index');
    }

    #[Route('/{file}/telecharger', name: 'admin_dev_backup_download', requirements: ['file' => '[a-z0-9\-]+\.sql\.gz'], methods: ['GET'])]
    public function download(string $file): Response
    {
        $path = $this->backup->path($file) ?? throw $this->createNotFoundException();

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/gzip');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file);

        return $response;
    }
}

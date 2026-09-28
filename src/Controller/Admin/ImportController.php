<?php

namespace App\Controller\Admin;

use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Service\Import\FootClubImportApplier;
use App\Service\Import\FootClubImportParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Import des licenciés depuis un export du logiciel "Foot Club" (FFF) au
 * format .xlsx : dépôt du fichier -> aperçu (ce qui va être créé/mis à
 * jour) -> confirmation qui écrit réellement en base.
 */
#[Route('/admin/import')]
class ImportController extends AbstractController
{
    private const SESSION_KEY = 'foot_club_import_pending';

    #[Route('', name: 'admin_import_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/import/upload.html.twig');
    }

    #[Route('/apercu', name: 'admin_import_preview', methods: ['POST'])]
    public function preview(
        Request $request,
        RequestStack $requestStack,
        FootClubImportParser $parser,
        FamilleRepository $familleRepository,
        LicencieRepository $licencieRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('import-upload', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('admin_import_index');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('fichier');
        if (!$file || !$file->isValid()) {
            $this->addFlash('error', "Merci de sélectionner un fichier Excel (.xlsx) valide.");

            return $this->redirectToRoute('admin_import_index');
        }

        try {
            $rows    = $parser->parseFile($file->getPathname());
            $preview = $parser->buildPreview($rows, $familleRepository, $licencieRepository);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Lecture du fichier impossible : '.$e->getMessage());

            return $this->redirectToRoute('admin_import_index');
        }

        if (empty($preview['groups'])) {
            $this->addFlash('error', "Le fichier ne contient aucune ligne exploitable.");

            return $this->redirectToRoute('admin_import_index');
        }

        $requestStack->getSession()->set(self::SESSION_KEY, $preview);

        return $this->render('admin/import/preview.html.twig', [
            'preview' => $preview,
        ]);
    }

    #[Route('/confirmer', name: 'admin_import_confirm', methods: ['POST'])]
    public function confirm(Request $request, RequestStack $requestStack, FootClubImportApplier $applier): Response
    {
        if (!$this->isCsrfTokenValid('import-confirm', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('admin_import_index');
        }

        $session = $requestStack->getSession();
        $preview = $session->get(self::SESSION_KEY);

        if (!\is_array($preview) || empty($preview['groups'])) {
            $this->addFlash('error', "Aucun import en attente (la session a peut-être expiré) : merci de déposer à nouveau le fichier.");

            return $this->redirectToRoute('admin_import_index');
        }

        // Un compte (avec mot de passe hashé) est créé par famille et par licencié importé : sur un
        // gros fichier, cela dépasse largement le délai par défaut (30 s). Non applicable si le serveur
        // impose sa propre limite (ex. max_execution_time verrouillé, PHP-FPM request_terminate_timeout).
        if (\function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $result = $applier->apply($preview);
        } catch (\Throwable $e) {
            $this->addFlash('error', "Échec de l'import : ".$e->getMessage());

            return $this->redirectToRoute('admin_import_index');
        }

        $session->remove(self::SESSION_KEY);

        $this->addFlash('success', sprintf(
            'Import terminé : %d famille(s) créée(s), %d mise(s) à jour, %d licencié(s) créé(s), %d mis à jour.',
            $result['famillesCreees'],
            $result['famillesMisesAJour'],
            $result['licenciesCrees'],
            $result['licenciesMisAJour'],
        ));

        return $this->redirectToRoute('admin_famille_index');
    }
}

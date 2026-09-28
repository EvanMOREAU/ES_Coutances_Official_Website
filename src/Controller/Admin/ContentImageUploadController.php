<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Point d'upload utilisé par l'éditeur riche (Trix) des champs de contenu :
 * permet de glisser-déposer / coller une image directement dans le texte,
 * sans passer par un système de blocs séparé.
 */
class ContentImageUploadController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    #[Route('/admin/content-image-upload', name: 'admin_content_image_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier reçu.'], 400);
        }

        if (!$file->isValid()) {
            $error = $file->getError();
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                    'Fichier trop volumineux pour la configuration du serveur (limite actuelle : %s). Réduisez la taille de l\'image ou contactez l\'administrateur du serveur.',
                    ini_get('upload_max_filesize')
                ),
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été envoyé que partiellement, réessayez.',
                default => 'Fichier invalide (erreur d\'upload).',
            };

            return new JsonResponse(['error' => $message], 400);
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return new JsonResponse(['error' => 'Fichier trop volumineux (5 Mo maximum).'], 400);
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(['error' => 'Type de fichier non autorisé (images uniquement).'], 400);
        }

        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $slugger = new AsciiSlugger();
        $baseName = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->lower()->toString();
        $filename = sprintf('%s-%s.%s', $baseName ?: 'image', bin2hex(random_bytes(6)), $extension);

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/pages/inline';
        $file->move($destination, $filename);

        return new JsonResponse(['url' => '/uploads/pages/inline/' . $filename]);
    }
}

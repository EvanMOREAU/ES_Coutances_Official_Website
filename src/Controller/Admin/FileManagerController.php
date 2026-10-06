<?php

namespace App\Controller\Admin;

use App\Entity\FileFavorite;
use App\Entity\User;
use App\Repository\FileFavoriteRepository;
use App\Service\FileManager\FileManagerException;
use App\Service\FileManager\FileStorage;
use App\Service\FileManager\OrphanFileFinder;
use App\Service\FileManager\UserDocumentSpace;
use App\Service\FileManager\ResolvedPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestionnaire de fichiers du back-office (voir FileStorage pour ce qui est exposé).
 *
 * La page est servie en HTML ; toutes les actions (liste, envoi, renommage…)
 * sont de petites requêtes JSON appelées par le contrôleur Stimulus « files ».
 */
#[Route('/admin/fichiers')]
class FileManagerController extends AbstractController
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly OrphanFileFinder $orphans,
        private readonly UserDocumentSpace $space,
        private readonly FileFavoriteRepository $favorites,
        private readonly EntityManagerInterface $em,
        private readonly string $filesQuotaMb,
    ) {
    }

    #[Route('', name: 'admin_files_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/files/index.html.twig', [
            'allowed_extensions' => FileStorage::ALLOWED_EXTENSIONS,
            'max_mb'             => FileStorage::MAX_UPLOAD_BYTES / 1048576,
        ]);
    }

    /** Contenu d'un dossier, ou vue « récents » / « favoris » / recherche. */
    #[Route('/api/liste', name: 'admin_files_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $view = (string) $request->query->get('vue', 'all');
            $query = trim((string) $request->query->get('q', ''));
            $path = $this->resolve((string) $request->query->get('path', ''));
            $user = $this->currentUser();
            $favoritePaths = array_flip($this->favorites->pathsFor($user));
            if ($path->virtual === UserDocumentSpace::ROOT) {
                $this->space->ensure($user); // premier passage : le dossier personnel est créé
            }

            if ($view === 'recent') {
                $entries = $this->storage->recent();
            } elseif ($view === 'orphans') {
                $entries = $this->orphans->find();
            } elseif ($view === 'starred') {
                $entries = $this->starredEntries(array_keys($favoritePaths));
            } elseif ($query !== '') {
                $entries = $this->storage->search($path, $query);
            } else {
                if (!$path->isVirtualRoot && !$path->exists && $path->real !== null) {
                    throw new FileManagerException('Dossier introuvable.');
                }
                $entries = $this->storage->listing($path);
            }

            // Dans « Documents », on ne montre à chacun que ce qui lui appartient (récents, recherche, favoris compris).
            $entries = array_values(array_filter($entries, fn (array $e) => $this->space->allows($user, (string) $e['path'])));

            foreach ($entries as &$entry) {
                $entry['starred'] = isset($favoritePaths[$entry['path']]);
            }
            unset($entry);

            if (in_array($view, ['starred', 'orphans'], true) && $query !== '') {
                $entries = array_values(array_filter($entries, static fn (array $e) => str_contains(mb_strtolower($e['name']), mb_strtolower($query))));
            }

            return $this->json([
                'path'       => $path->virtual,
                'parent'     => $path->isVirtualRoot ? null : $path->parent(),
                'readOnly'   => $path->readOnly,
                'canWrite'   => !$path->readOnly && !$path->isVirtualRoot,
                'breadcrumb' => $this->breadcrumb($path),
                'entries'    => $entries,
            ]);
        } catch (FileManagerException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /** Espace utilisé (calcul séparé : il parcourt tous les dossiers, on ne le refait pas à chaque clic). */
    #[Route('/api/stockage', name: 'admin_files_usage', methods: ['GET'])]
    public function usage(): JsonResponse
    {
        return $this->json($this->storage->usage(max(1, (int) $this->filesQuotaMb) * 1048576));
    }

    #[Route('/api/dossier', name: 'admin_files_mkdir', methods: ['POST'])]
    public function createFolder(Request $request): JsonResponse
    {
        return $this->action($request, function (Request $request): array {
            $parent = $this->resolve((string) $request->request->get('path', ''));
            $path   = $this->storage->createFolder($parent, (string) $request->request->get('name', ''));

            return ['path' => $path];
        });
    }

    #[Route('/api/envoyer', name: 'admin_files_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        return $this->action($request, function (Request $request): array {
            $parent = $this->resolve((string) $request->request->get('path', ''));

            $uploaded = [];
            $errors   = [];
            foreach ($request->files->all('files') as $file) {
                try {
                    $uploaded[] = $this->storage->upload($parent, $file);
                } catch (FileManagerException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            if ($uploaded === [] && $errors === []) {
                $errors[] = 'Aucun fichier reçu (dépasse-t-il la taille maximale autorisée par le serveur ?).';
            }

            return ['uploaded' => $uploaded, 'errors' => $errors];
        });
    }

    #[Route('/api/renommer', name: 'admin_files_rename', methods: ['POST'])]
    public function rename(Request $request): JsonResponse
    {
        return $this->action($request, function (Request $request): array {
            $item = $this->resolve((string) $request->request->get('path', ''));
            $to   = $this->storage->rename($item, (string) $request->request->get('name', ''));
            $this->favorites->move($item->virtual, $to);

            return ['path' => $to];
        });
    }

    #[Route('/api/supprimer', name: 'admin_files_delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        return $this->action($request, function (Request $request): array {
            $item = $this->resolve((string) $request->request->get('path', ''));
            $this->storage->delete($item);
            $this->favorites->forget($item->virtual);

            return [];
        });
    }

    /** Supprime d'un coup tous les fichiers morts (la liste est recalculée ici, pas reprise du navigateur). */
    #[Route('/api/orphelins/supprimer', name: 'admin_files_delete_orphans', methods: ['POST'])]
    public function deleteOrphans(Request $request): JsonResponse
    {
        return $this->action($request, function (): array {
            $deleted = 0;
            $freed   = 0;
            foreach ($this->orphans->find() as $entry) {
                try {
                    $item = $this->resolve($entry['path']);
                    $this->storage->delete($item);
                    $this->favorites->forget($item->virtual);
                    ++$deleted;
                    $freed += (int) ($entry['size'] ?? 0);
                } catch (FileManagerException) {
                    // Fichier déjà parti ou verrouillé : on passe au suivant.
                }
            }

            return ['deleted' => $deleted, 'freed' => $freed];
        });
    }

    #[Route('/api/favori', name: 'admin_files_star', methods: ['POST'])]
    public function star(Request $request): JsonResponse
    {
        return $this->action($request, function (Request $request): array {
            $item = $this->resolve((string) $request->request->get('path', ''));
            if ($item->isVirtualRoot || !$item->exists && $item->real !== null) {
                throw new FileManagerException('Élément introuvable.');
            }

            $user     = $this->currentUser();
            $existing = $this->favorites->findOneBy(['user' => $user, 'path' => $item->virtual]);
            if ($existing !== null) {
                $this->em->remove($existing);
            } else {
                $this->em->persist(new FileFavorite($user, $item->virtual));
            }
            $this->em->flush();

            return ['starred' => $existing === null];
        });
    }

    /** Affichage dans le navigateur (aperçu, miniatures). */
    #[Route('/voir', name: 'admin_files_view', methods: ['GET'])]
    public function view(Request $request): Response
    {
        return $this->serve($request, inline: true);
    }

    #[Route('/telecharger', name: 'admin_files_download', methods: ['GET'])]
    public function download(Request $request): Response
    {
        return $this->serve($request, inline: false);
    }

    private function serve(Request $request, bool $inline): Response
    {
        try {
            $file = $this->resolve((string) $request->query->get('path', ''));
        } catch (FileManagerException) {
            throw $this->createNotFoundException();
        }
        if ($file->isVirtualRoot || !$file->exists || $file->real === null || !is_file($file->real)) {
            throw $this->createNotFoundException();
        }

        $ext = strtolower(pathinfo($file->real, \PATHINFO_EXTENSION));
        $kind = $this->storage->kindOf($ext);
        $previewable = in_array($kind, ['image', 'pdf', 'text', 'video', 'audio'], true);

        $response = new BinaryFileResponse($file->real);
        $response->headers->set('Content-Type', $this->storage->mimeOf($file->real));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', $file->isPrivate ? 'private, no-store' : 'private, max-age=60');
        // Un SVG ouvert directement ne doit pas pouvoir exécuter de script.
        if ($ext === 'svg') {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }
        $response->setContentDisposition(
            $inline && $previewable ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($file->real),
            $this->asciiFallback(basename($file->real)),
        );

        return $response;
    }

    private function asciiFallback(string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'fichier';

        return str_replace(['%', '/', '\\'], '_', $ascii);
    }

    /**
     * Exécute une action d'écriture : jeton CSRF vérifié, erreurs « métier » renvoyées proprement.
     *
     * @param callable(Request): array<string, mixed> $callback
     */
    private function action(Request $request, callable $callback): JsonResponse
    {
        if (!$this->isCsrfTokenValid('files', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Session expirée : rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        try {
            return $this->json(['ok' => true] + $callback($request));
        } catch (FileManagerException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param list<string> $paths
     *
     * @return list<array<string, mixed>>
     */
    private function starredEntries(array $paths): array
    {
        $entries = [];
        foreach ($paths as $virtual) {
            try {
                $resolved = $this->resolve($virtual);
            } catch (FileManagerException) {
                continue;
            }
            if ($resolved->isVirtualRoot || !$resolved->exists || $resolved->real === null) {
                // Favori dont le fichier a disparu depuis : on l'oublie discrètement.
                if (!$resolved->isVirtualRoot && $resolved->real !== null) {
                    $this->favorites->forget($virtual);
                }
                continue;
            }

            $parent = $this->resolve($resolved->parent());
            foreach ($this->storage->listing($parent) as $entry) {
                if ($entry['path'] === $resolved->virtual) {
                    $entries[] = $entry;
                    break;
                }
            }
        }

        return $entries;
    }

    /** @return list<array{name: string, path: string}> */
    private function breadcrumb(ResolvedPath $path): array
    {
        $crumbs = [];
        $accumulated = '';
        foreach ($path->virtual === '' ? [] : explode('/', $path->virtual) as $segment) {
            $accumulated .= ($accumulated === '' ? '' : '/').$segment;
            $crumbs[] = ['name' => $this->labelFor($accumulated, $segment), 'path' => $accumulated];
        }

        return $crumbs;
    }

    private function labelFor(string $path, string $segment): string
    {
        return $path === $segment
            ? match ($segment) {
                'documents'        => 'Documents',
                'images'           => 'Images',
                'fichiers-du-site' => 'Fichiers',
                default            => $segment,
            }
            : $segment;
    }

    /**
     * Résout un chemin du gestionnaire en y ajoutant la règle de « Documents » : chacun n'accède
     * qu'à son propre dossier, sauf autorisation « fichiers.documents_tous ».
     */
    private function resolve(string $virtual): ResolvedPath
    {
        $resolved = $this->storage->resolve($virtual);
        if (!$this->space->allows($this->currentUser(), $resolved->virtual)) {
            throw new FileManagerException('Chemin non autorisé.');
        }

        return $resolved;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}

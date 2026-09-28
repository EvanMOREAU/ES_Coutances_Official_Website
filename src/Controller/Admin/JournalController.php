<?php

namespace App\Controller\Admin;

use App\Audit\AuditCatalog;
use App\Audit\AuditRecorder;
use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Journal d'activité, réservé aux développeurs : qui a fait quoi, quand, depuis quelle adresse IP,
 * avec les valeurs avant / après. En lecture seule : aucune ligne ne peut être modifiée ni supprimée
 * depuis l'application. L'export CSV (lui-même journalisé) sert de pièce en cas de litige.
 */
#[Route('/admin/journal')]
#[IsGranted('ROLE_DEV')]
class JournalController extends AbstractController
{
    private const FILTERS = ['q', 'user', 'type', 'operation', 'category', 'ip', 'entity', 'entityId', 'from', 'to', 'errors', 'request'];

    public function __construct(private readonly AuditLogRepository $repository)
    {
    }

    #[Route('', name: 'admin_journal_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $page    = max(1, $request->query->getInt('page', 1));
        $result  = $this->repository->search($filters, $page);

        return $this->render('admin/journal/index.html.twig', [
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'pages'      => max(1, (int) ceil($result['total'] / AuditLogRepository::PER_PAGE)),
            'filters'    => $filters,
            'users'      => $this->repository->users(),
            'categories' => array_values(array_unique(array_merge(AuditCatalog::categories(), $this->repository->usedCategories()))),
            'types'      => AuditLog::TYPES,
            'operations' => AuditLog::OPERATIONS,
            'perPage'    => AuditLogRepository::PER_PAGE,
        ]);
    }

    #[Route('/export', name: 'admin_journal_export', methods: ['GET'])]
    public function export(Request $request, AuditRecorder $recorder): Response
    {
        $filters = $this->filters($request);
        // L'export est lui-même une action tracée : on sait qui a extrait quoi.
        $recorder->event(AuditLog::TYPE_REQUETE, 'telechargement', 'Système', 'Export CSV du journal d\'activité', null, ['filtres' => array_filter($filters)]);

        $response = new StreamedResponse(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 avec BOM : Excel lit correctement les accents
            fputcsv($out, ['Date', 'Type', 'Opération', 'Rubrique', 'Résumé', 'Élément', 'Identifiant', 'Utilisateur', 'E-mail', 'Rôles', 'IP', 'Transféré par', 'Navigateur', 'Méthode', 'Route', 'Adresse', 'Code HTTP', 'Requête', 'Modifications', 'Détails'], ';');
            foreach ($this->repository->export($filters) as $log) {
                fputcsv($out, [
                    $log->getOccurredAt()?->format('Y-m-d H:i:s'), $log->getTypeLabel(), $log->getOperationLabel(), $log->getCategory(), $log->getSummary(),
                    $log->getEntityShort(), $log->getEntityId(), $log->getUserName(), $log->getUserEmail(), $log->getUserRoles(), $log->getIp(), $log->getForwardedFor(),
                    $log->getUserAgent(), $log->getMethod(), $log->getRoute(), $log->getPath(), $log->getStatusCode(), $log->getRequestId(),
                    $log->getChanges() ? json_encode($log->getChanges(), JSON_UNESCAPED_UNICODE) : '', $log->getContext() ? json_encode($log->getContext(), JSON_UNESCAPED_UNICODE) : '',
                ], ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="journal-'.date('Ymd-His').'.csv"');

        return $response;
    }

    #[Route('/{id}', name: 'admin_journal_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(string $id): Response
    {
        $log = $this->repository->find($id) ?? throw $this->createNotFoundException();

        return $this->render('admin/journal/show.html.twig', [
            'log'     => $log,
            'related' => $log->getRequestId() ? array_values(array_filter($this->repository->forRequest($log->getRequestId()), static fn (AuditLog $r) => $r->getId() !== $log->getId())) : [],
            'history' => $log->getEntityClass() && $log->getEntityId() ? $this->repository->search(['entity' => $log->getEntityShort(), 'entityId' => $log->getEntityId()], 1, 15)['rows'] : [],
        ]);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $filters = [];
        foreach (self::FILTERS as $key) {
            $filters[$key] = trim((string) $request->query->get($key, ''));
        }

        return $filters;
    }
}

<?php

namespace App\Audit;

use App\Entity\AuditLog;
use App\Security\PermissionCatalog;
use App\Security\RoutePermissions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Journalise les actions demandées par les utilisateurs (toute requête qui modifie quelque chose, plus
 * les téléchargements de fichiers), les accès refusés et les erreurs serveur. Les changements de
 * données qu'une action provoque sont, eux, écrits par EntityAuditListener avec le même identifiant
 * de requête, ce qui permet de tout retrouver depuis la fiche d'une action.
 */
final class RequestAuditSubscriber implements EventSubscriberInterface
{
    /** Routes GET à tracer (consultation de documents). */
    private const TRACKED_GET = ['admin_files_download', 'admin_files_view'];

    /** Routes jamais tracées (interrogations régulières, préférences d'affichage). */
    private const IGNORED = ['admin_table_preferences', 'admin_notification_read', 'admin_notification_dismiss', 'admin_chat_sync', 'portail_chat_sync', 'admin_chat_thread', 'portail_chat_thread'];

    /** Routes dont les paramètres ne sont pas enregistrés (contenu privé). */
    private const NO_PARAMS_PREFIXES = ['admin_chat_', 'portail_chat_', 'app_login', 'portail_login', 'app_reset_password', 'app_forgot_password'];

    public function __construct(private readonly AuditRecorder $recorder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE  => ['onResponse', -10],
            KernelEvents::EXCEPTION => ['onException', 0],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || '' === $route || str_starts_with($route, '_') || in_array($route, self::IGNORED, true)) {
            return;
        }

        $method   = $request->getMethod();
        $download = in_array($route, self::TRACKED_GET, true);
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && !$download) {
            return;
        }

        $status = $event->getResponse()->getStatusCode();
        [$category, $label] = $this->describe($route, $request);

        $this->recorder->event(
            AuditLog::TYPE_REQUETE,
            $download ? 'telechargement' : 'action',
            $category,
            sprintf('%s (%s %s → %d)', $label, $method, $request->getPathInfo(), $status),
            null,
            $this->context($request, $route),
            null,
            null,
            null,
            $status,
        );
    }

    public function onException(ExceptionEvent $event): void
    {
        $request   = $event->getRequest();
        $throwable = $event->getThrowable();
        $route     = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest()) {
            return;
        }

        if ($throwable instanceof AccessDeniedHttpException || $throwable instanceof AccessDeniedException) {
            $this->recorder->event(
                AuditLog::TYPE_SECURITE,
                'refus',
                'Sécurité',
                sprintf('%s %s', $request->getMethod(), $request->getPathInfo()),
                null,
                ['route' => $route, 'motif' => $throwable->getMessage()],
                null,
                null,
                null,
                403,
            );

            return;
        }

        // Erreurs serveur (pas les 404 des robots) : utile pour comprendre un incident.
        if (!$throwable instanceof HttpExceptionInterface) {
            $this->recorder->event(
                AuditLog::TYPE_SYSTEME,
                'echec',
                'Système',
                sprintf('Erreur serveur : %s', (new \ReflectionClass($throwable))->getShortName()),
                null,
                ['route' => $route, 'message' => $throwable->getMessage(), 'fichier' => basename($throwable->getFile()).':'.$throwable->getLine()],
                null,
                null,
                null,
                500,
            );
        }
    }

    /** @return array{0: string, 1: string} rubrique et libellé de l'action */
    private function describe(string $route, Request $request): array
    {
        if (str_starts_with($route, 'admin')) {
            $required = RoutePermissions::required($route, $request->getMethod(), (array) $request->attributes->get('_route_params', []), $request);
            if (is_string($required) && !str_contains($required, ',') && PermissionCatalog::exists($required)) {
                foreach (PermissionCatalog::groups() as $group => $resources) {
                    $resource = explode('.', $required)[0];
                    if (isset($resources[$resource])) {
                        return [$this->adminCategory($route, $group), PermissionCatalog::label($required)];
                    }
                }
            }

            return [$this->adminCategory($route, 'Administration'), 'Action « '.$route.' »'];
        }

        return match (true) {
            str_starts_with($route, 'boutique_')  => ['Boutique (site public)', 'Boutique : '.$route],
            str_starts_with($route, 'portail_')   => ['Espace licenciés', 'Espace licenciés : '.$route],
            str_starts_with($route, 'app_login'), str_starts_with($route, 'app_forgot'), str_starts_with($route, 'app_reset'), str_starts_with($route, 'app_logout') => ['Sécurité', 'Authentification : '.$route],
            'app_contact' === $route             => ['Autre', 'Formulaire de contact'],
            default                               => ['Autre', $route],
        };
    }

    private function adminCategory(string $route, string $fallback): string
    {
        return match (true) {
            str_starts_with($route, 'admin_article') => 'Boutique — articles',
            str_starts_with($route, 'admin_commande') => 'Boutique — commandes',
            str_starts_with($route, 'admin_files')    => 'Fichiers',
            str_starts_with($route, 'admin_chat')     => 'Messagerie',
            str_starts_with($route, 'admin_import')   => 'Import',
            str_starts_with($route, 'admin_adhesion') => 'Paiements des licences',
            default                                    => $fallback,
        };
    }

    /** @return array<string, mixed> */
    private function context(Request $request, string $route): array
    {
        $context = ['route' => $route, 'parametres_route' => array_filter((array) $request->attributes->get('_route_params', []), static fn ($v) => is_scalar($v))];
        foreach (self::NO_PARAMS_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return $context;
            }
        }

        $body = $request->request->all();
        if ([] !== $body) {
            $context['donnees_envoyees'] = $body;
        } elseif (str_contains((string) $request->headers->get('Content-Type'), 'json') && '' !== $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $context['donnees_envoyees'] = $decoded;
            }
        }
        if ($request->query->count() > 0) {
            $context['requete'] = $request->query->all();
        }
        $files = [];
        foreach ($request->files->all() as $field => $file) {
            foreach (is_array($file) ? $file : [$file] as $item) {
                if ($item instanceof UploadedFile) {
                    try {
                        $size = $item->getSize();
                    } catch (\RuntimeException) {
                        $size = null; // le fichier a déjà été déplacé par le contrôleur
                    }
                    $files[$field][] = ['nom' => $item->getClientOriginalName(), 'taille' => $size, 'type' => $item->getClientMimeType()];
                }
            }
        }
        if ($files) {
            $context['fichiers'] = $files;
        }

        return $context;
    }
}

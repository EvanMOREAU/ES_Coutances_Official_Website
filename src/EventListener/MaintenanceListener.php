<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class MaintenanceListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $maintenanceFlagPath,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !is_file($this->maintenanceFlagPath)) {
            return;
        }

        $response = new Response(
            $this->twig->render('default/maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
        $response->headers->set('Retry-After', '3600');

        $event->setResponse($response);
    }
}

<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
class MaintenanceListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $maintenanceFlagPath,
    ) {
    }

    public function __invoke(RequestEvent $event): void
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

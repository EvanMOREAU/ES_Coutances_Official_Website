<?php

namespace App\Translation;

use App\Service\Locale\Locales;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pages publiques dans une autre langue que le français : les textes écrits en dur dans les gabarits sont remplacés
 * par leur traduction enregistrée en base. L'administration reste en français.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final class PageTranslationListener
{
    public function __construct(private readonly HtmlTranslator $html)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request  = $event->getRequest();
        $response = $event->getResponse();
        $locale   = $request->getLocale();

        if (!$event->isMainRequest() || Locales::DEFAULT === $locale || !Locales::isSupported($locale)) {
            return;
        }
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse || !$response->isSuccessful()) {
            return;
        }
        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/_')) {
            return;
        }

        $content = $response->getContent();
        if (\is_string($content) && '' !== $content) {
            $response->setContent($this->html->translate($content, $locale));
        }
    }
}

<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\Locale\LocaleRegistry;
use App\Service\Locale\Locales;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Choisit la langue de la requête : celle du compte connecté, sinon celle mémorisée dans le cookie du
 * visiteur, sinon le français. S'exécute après le pare-feu (priorité 8) pour connaître l'utilisateur.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final class LocaleListener
{
    public function __construct(
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LocaleRegistry $locales,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user    = $this->security->getUser();

        $locale = $user instanceof User && $this->locales->isEnabled($user->getLocale())
            ? $user->getLocale()
            : $this->locales->normalize((string) $request->cookies->get(Locales::COOKIE));

        $request->setLocale($locale);
        if ($this->translator instanceof LocaleAwareInterface) {
            $this->translator->setLocale($locale);
        }
    }
}

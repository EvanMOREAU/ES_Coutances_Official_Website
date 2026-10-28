<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Locale\LocaleRegistry;
use App\Service\Locale\Locales;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Changement de langue depuis n'importe quelle page : mémorisé dans un cookie et, si connecté, sur le compte. */
final class LocaleController extends AbstractController
{
    #[Route('/langue/{locale}', name: 'app_locale_switch', requirements: ['locale' => '[a-z]{2}'], methods: ['GET'])]
    public function __invoke(Request $request, string $locale, EntityManagerInterface $em, LocaleRegistry $locales): RedirectResponse
    {
        if (!$locales->isEnabled($locale)) {
            throw new NotFoundHttpException();
        }

        $user = $this->getUser();
        if ($user instanceof User && $user->getLocale() !== $locale) {
            $user->setLocale($locale);
            $em->flush();
        }

        $response = new RedirectResponse($this->safeBack($request));
        $response->headers->setCookie(Cookie::create(Locales::COOKIE, $locale, new \DateTimeImmutable('+1 year'), '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));

        return $response;
    }

    /** Retour à la page précédente, uniquement si elle appartient au site (pas de redirection ouverte). */
    private function safeBack(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');
        if ('' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost().'/')) {
            return $referer;
        }

        return $this->generateUrl('app_home');
    }
}

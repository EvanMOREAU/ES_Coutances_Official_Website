<?php

namespace App\Controller\Security;

use App\Service\Webauthn\WebauthnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Partie publique (avant authentification) de la connexion par clé d'accès : génère le défi
 * envoyé au navigateur. La vérification de la réponse est interceptée par
 * App\Security\Webauthn\PasskeyAuthenticator avant d'atteindre webauthnLoginCheck() ci-dessous.
 */
class WebauthnLoginController extends AbstractController
{
    #[Route('/admin/webauthn/login/options', name: 'admin_webauthn_login_options', methods: ['GET'])]
    #[Route('/mon-compte/webauthn/login/options', name: 'portail_webauthn_login_options', methods: ['GET'])]
    public function options(WebauthnService $webauthn): JsonResponse
    {
        $options = $webauthn->generateLoginOptions();

        return JsonResponse::fromJsonString($webauthn->optionsToJson($options));
    }

    #[Route('/admin/webauthn/login', name: 'admin_webauthn_login_check', methods: ['POST'])]
    #[Route('/mon-compte/webauthn/login', name: 'portail_webauthn_login_check', methods: ['POST'])]
    public function check(): never
    {
        throw new \LogicException('Cette route est interceptée par PasskeyAuthenticator et ne doit jamais être atteinte.');
    }
}

<?php

namespace App\Security\Webauthn;

use App\Service\Webauthn\WebauthnService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Connexion sans mot de passe via une clé d'accès (passkey) déjà enregistrée : la vérification
 * cryptographique de la réponse WebAuthn (voir WebauthnService::verifyLogin) prouve l'identité,
 * donc un passeport auto-validé suffit — comme pour un login par lien magique.
 */
class PasskeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly WebauthnService $webauthn,
        private readonly RouterInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'admin_webauthn_login_check' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);
        $response = is_array($data) ? ($data['response'] ?? null) : null;
        if (!is_array($response)) {
            throw new CustomUserMessageAuthenticationException('Requête invalide.');
        }

        try {
            $credential = $this->webauthn->verifyLogin((string) json_encode($response));
        } catch (\Throwable) {
            throw new CustomUserMessageAuthenticationException("Clé d'accès refusée.");
        }

        $user = $credential->getUser();

        return new SelfValidatingPassport(new UserBadge((string) $user->getUserIdentifier(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new JsonResponse(['redirect' => $this->router->generate('admin')]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessage()], 401);
    }
}

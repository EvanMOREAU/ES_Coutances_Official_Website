<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Garde des actions sensibles (mise à jour du site) : le compte doit être protégé par une seconde
 * authentification (indépendamment de ENFORCE_PRIVILEGED_2FA) et avoir ressaisi son mot de passe
 * dans les dernières minutes. La ressaisie est limitée en nombre d'essais (anti force brute).
 */
class Reauthentication
{
    private const SESSION_KEY = 'reauth_at';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly WebauthnCredentialRepository $passkeys,
        #[Autowire(service: 'limiter.reauth')] private readonly RateLimiterFactoryInterface $limiter,
        #[Autowire('%env(int:REAUTH_TTL)%')] private readonly int $ttl = 300,
    ) {
    }

    public function hasSecondFactor(User $user): bool
    {
        return $user->hasTwoFactorEnabled() || [] !== $this->passkeys->findAllForUser($user);
    }

    public function isFresh(SessionInterface $session): bool
    {
        $at = $session->get(self::SESSION_KEY);

        return \is_int($at) && time() - $at <= $this->ttl;
    }

    /**
     * @return bool vrai si le mot de passe est correct (la ressaisie est alors mémorisée en session)
     *
     * @throws \RuntimeException trop d'essais
     */
    public function confirm(User $user, string $password, SessionInterface $session): bool
    {
        $limit = $this->limiter->create((string) $user->getId())->consume();
        if (!$limit->isAccepted()) {
            throw new \RuntimeException('Trop de tentatives. Réessayez dans quelques minutes.');
        }

        if ('' === $password || !$this->hasher->isPasswordValid($user, $password)) {
            return false;
        }

        $session->set(self::SESSION_KEY, time());

        return true;
    }

    public function forget(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }
}

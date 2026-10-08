<?php

namespace App\Security\TwoFactor;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/**
 * Représente un secret TOTP généré mais pas encore confirmé par l'utilisateur (étape
 * d'activation dans les paramètres de sécurité). Permet de générer le QR code et de
 * vérifier le premier code saisi sans modifier l'entité User tant que ce n'est pas confirmé.
 */
final readonly class PendingTotpSecret implements TwoFactorInterface
{
    public function __construct(
        private string $username,
        private string $secret,
    ) {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}

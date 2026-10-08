<?php

namespace App\Security;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Valeur aléatoire propre à chaque requête, autorisant les scripts en ligne du site dans la
 * Content-Security-Policy (voir SecurityHeadersListener). Elle est tirée à la première utilisation
 * — le plus souvent par la fonction Twig `csp_nonce()` — et change à chaque requête.
 */
final class CspNonce implements ResetInterface
{
    private ?string $value = null;

    public function value(): string
    {
        return $this->value ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public function isUsed(): bool
    {
        return null !== $this->value;
    }

    public function reset(): void
    {
        $this->value = null;
    }
}

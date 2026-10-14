<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Valeur aléatoire autorisant les scripts et styles en ligne du site dans la Content-Security-Policy (voir
 * SecurityHeadersListener). Elle est tirée à la première utilisation — le plus souvent par la fonction Twig
 * `csp_nonce()` — et change à chaque chargement complet de page.
 *
 * Exception : les visites Turbo (navigation sans rechargement dans l'administration et l'espace connecté). Le
 * navigateur conserve la politique de la première page ; si chaque réponse portait un nouveau nonce, les
 * `<style>` et `<script>` de la page suivante (et ceux que Turbo ou Trix créent eux-mêmes) seraient refusés.
 * Le script du site (assets/csp_nonce.js) renvoie donc le nonce courant dans l'en-tête X-Csp-Nonce, et le
 * serveur le reprend s'il a la forme attendue. Seul un script du site, donc déjà autorisé, peut poser cet
 * en-tête : un autre site ne le peut pas (requête interdite sans autorisation CORS).
 */
final class CspNonce implements ResetInterface
{
    public const HEADER = 'X-Csp-Nonce';

    private ?string $value = null;

    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function value(): string
    {
        return $this->value ??= $this->fromRequest() ?? rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public function isUsed(): bool
    {
        return null !== $this->value;
    }

    public function reset(): void
    {
        $this->value = null;
    }

    /** Nonce renvoyé par le navigateur lors d'une visite Turbo (18 octets en base64 « url-safe », soit 24 caractères). */
    private function fromRequest(): ?string
    {
        $request = $this->requests->getMainRequest();
        $sent = $request?->headers->get(self::HEADER);

        return \is_string($sent) && 1 === preg_match('/^[A-Za-z0-9_-]{24}$/', $sent) ? $sent : null;
    }
}

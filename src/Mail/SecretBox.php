<?php

namespace App\Mail;

/**
 * Chiffre les secrets stockés en base (mot de passe SMTP) avec une clé dérivée de APP_SECRET :
 * une copie de la base seule ne suffit pas à les lire. Changer APP_SECRET les rend illisibles
 * (il faudra alors ressaisir le mot de passe).
 */
class SecretBox
{
    private const CIPHER = 'aes-256-gcm';

    public function __construct(private readonly string $secret)
    {
    }

    public function encrypt(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $encrypted) {
            throw new \RuntimeException('Chiffrement impossible.');
        }

        return base64_encode($iv.$tag.$encrypted);
    }

    /** @return string|null null si la valeur est illisible (clé changée, donnée corrompue) */
    public function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if (false === $raw || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return false === $plain ? null : $plain;
    }

    private function key(): string
    {
        return hash('sha256', 'mail-settings|'.$this->secret, true);
    }
}

<?php

namespace App\Tests\Unit\Mail;

use App\Mail\SecretBox;
use PHPUnit\Framework\TestCase;

final class SecretBoxTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $box = new SecretBox('secret');

        $encrypted = $box->encrypt('mot-de-passe-smtp');

        self::assertNotSame('mot-de-passe-smtp', $encrypted);
        self::assertSame('mot-de-passe-smtp', $box->decrypt($encrypted));
    }

    public function testEncryptionIsRandomised(): void
    {
        $box = new SecretBox('secret');

        self::assertNotSame($box->encrypt('x'), $box->encrypt('x'));
    }

    public function testDecryptWithAnotherSecretFails(): void
    {
        $encrypted = (new SecretBox('a'))->encrypt('valeur');

        self::assertNull((new SecretBox('b'))->decrypt($encrypted));
    }

    public function testDecryptRejectsGarbage(): void
    {
        $box = new SecretBox('secret');

        self::assertNull($box->decrypt('pas-du-base64!!'));
        self::assertNull($box->decrypt(base64_encode('trop court')));
    }

    public function testDecryptDetectsTampering(): void
    {
        $box = new SecretBox('secret');
        $raw = base64_decode($box->encrypt('valeur'), true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";

        self::assertNull($box->decrypt(base64_encode($raw)));
    }
}

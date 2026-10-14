<?php

namespace App\Tests\Unit\Security;

use App\Entity\WebauthnCredential;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/** Les clés d'accès sont stockées sérialisées : seules les classes attendues peuvent en être reconstruites. */
final class WebauthnUnserializeTest extends TestCase
{
    private function record(): CredentialRecord
    {
        return CredentialRecord::create('cred-id', 'public-key', ['internal'], 'none', new EmptyTrustPath(), Uuid::v4(), 'cle-publique', 'handle', 3);
    }

    public function testRecordRoundTrips(): void
    {
        $credential = (new WebauthnCredential())->setRecord($this->record());

        $restored = $credential->getRecord();

        self::assertSame('cred-id', $restored->publicKeyCredentialId);
        self::assertSame(3, $restored->counter);
    }

    public function testForeignClassesAreNotInstantiated(): void
    {
        $credential = new WebauthnCredential();
        $property   = new \ReflectionProperty(WebauthnCredential::class, 'donnees');
        $property->setValue($credential, base64_encode(serialize(new \ArrayObject(['x' => 1]))));

        $this->expectException(\RuntimeException::class);

        $credential->getRecord();
    }
}

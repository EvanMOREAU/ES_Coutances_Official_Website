<?php

namespace App\Service\Webauthn;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Enregistrement et vérification des clés d'accès (passkeys) WebAuthn, pour se connecter
 * au back-office sans mot de passe (voir Security\Webauthn\PasskeyAuthenticator). Les options
 * de chaque cérémonie (enregistrement / connexion) sont gardées en session le temps de
 * l'aller-retour avec le navigateur, puis consommées à la vérification.
 */
class WebauthnService
{
    private const SESSION_CREATION_OPTIONS = 'webauthn_creation_options';
    private const SESSION_REQUEST_OPTIONS = 'webauthn_request_options';

    private readonly AttestationStatementSupportManager $attestationStatementSupportManager;
    private readonly SerializerInterface $serializer;
    private readonly AuthenticatorAttestationResponseValidator $attestationValidator;
    private readonly AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WebauthnCredentialRepository $credentials,
        private readonly RequestStack $requestStack,
        private readonly string $rpName = 'Les Scouts d\'Escoutances',
    ) {
        $this->attestationStatementSupportManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);
        $this->serializer = (new WebauthnSerializerFactory($this->attestationStatementSupportManager))->create();

        $algorithms = Manager::create()->add(ES256::create(), RS256::create());
        $factory = new CeremonyStepManagerFactory();
        $factory->setAttestationStatementSupportManager($this->attestationStatementSupportManager);
        $factory->setAlgorithmManager($algorithms);

        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
    }

    // --- Enregistrement d'une nouvelle clé ------------------------------------

    public function generateRegistrationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        $userHandle = $user->getWebauthnUserHandle();
        if (null === $userHandle) {
            $userHandle = self::base64UrlEncode(random_bytes(32));
            $user->setWebauthnUserHandle($userHandle);
            $this->em->flush();
        }

        $exclude = array_map(
            static fn (WebauthnCredential $c) => $c->getRecord()->getPublicKeyCredentialDescriptor(),
            $this->credentials->findAllForUser($user),
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $this->rp(),
            PublicKeyCredentialUserEntity::create(
                $user->getEmail() ?? '',
                self::base64UrlDecode($userHandle),
                $user->getNomComplet() ?: ($user->getEmail() ?? ''),
            ),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::createPk(-7),   // ES256
                PublicKeyCredentialParameters::createPk(-257), // RS256
            ],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $exclude,
            60000,
        );

        $this->requestStack->getSession()->set(self::SESSION_CREATION_OPTIONS, serialize($options));

        return $options;
    }

    public function optionsToJson(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string
    {
        return $this->serializer->serialize($options, 'json');
    }

    public function verifyRegistration(User $user, string $responseJson, string $label): WebauthnCredential
    {
        $options = $this->consumeSessionOptions(self::SESSION_CREATION_OPTIONS, PublicKeyCredentialCreationOptions::class);

        $publicKeyCredential = $this->serializer->deserialize($responseJson, PublicKeyCredential::class, 'json');
        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException("Réponse d'enregistrement invalide.");
        }

        $credentialRecord = $this->attestationValidator->check($response, $options, $this->host());

        $credential = new WebauthnCredential();
        $credential->setUser($user)
            ->setCredentialId(self::base64UrlEncode($credentialRecord->publicKeyCredentialId))
            ->setRecord($credentialRecord)
            ->setLabel('' !== trim($label) ? mb_substr(trim($label), 0, 100) : 'Clé d\'accès');

        $this->em->persist($credential);
        $this->em->flush();

        return $credential;
    }

    // --- Connexion par clé existante -------------------------------------------

    public function generateLoginOptions(): PublicKeyCredentialRequestOptions
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->host(),
            [], // liste vide : la clé est « discoverable », l'utilisateur est choisi côté authentificateur
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            60000,
        );

        $this->requestStack->getSession()->set(self::SESSION_REQUEST_OPTIONS, serialize($options));

        return $options;
    }

    /** @throws \RuntimeException si la clé est inconnue ou la vérification échoue */
    public function verifyLogin(string $responseJson): WebauthnCredential
    {
        $options = $this->consumeSessionOptions(self::SESSION_REQUEST_OPTIONS, PublicKeyCredentialRequestOptions::class);

        $publicKeyCredential = $this->serializer->deserialize($responseJson, PublicKeyCredential::class, 'json');
        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Réponse de connexion invalide.');
        }

        $credential = $this->credentials->findOneByCredentialId(self::base64UrlEncode($publicKeyCredential->rawId));
        if (null === $credential) {
            throw new \RuntimeException('Clé d\'accès inconnue.');
        }

        $credentialRecord = $credential->getRecord();
        $this->assertionValidator->check($credentialRecord, $response, $options, $this->host(), $response->userHandle);

        $credential->setRecord($credentialRecord)->marquerUtilisee();
        $this->em->flush();

        return $credential;
    }

    // --- Utilitaires -------------------------------------------------------------

    private function consumeSessionOptions(string $key, string $expectedClass): object
    {
        $session = $this->requestStack->getSession();
        $serialized = $session->get($key);
        $session->remove($key);
        if (!is_string($serialized)) {
            throw new \RuntimeException('Aucune cérémonie WebAuthn en cours : recommencez.');
        }

        $options = unserialize($serialized, ['allowed_classes' => true]);
        if (!$options instanceof $expectedClass) {
            throw new \RuntimeException('Options WebAuthn invalides : recommencez.');
        }

        return $options;
    }

    private function rp(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create($this->rpName, $this->host());
    }

    private function host(): string
    {
        return $this->requestStack->getMainRequest()?->getHost() ?? '';
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if (0 !== $pad) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}

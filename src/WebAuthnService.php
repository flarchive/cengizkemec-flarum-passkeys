<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

class WebAuthnService
{
    private const NIL_UUID = '00000000-0000-0000-0000-000000000000';

    private const CHALLENGE_LIFETIME = 300;

    private ?SerializerInterface $serializer = null;

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('passkeys.enabled', true);
    }

    public function isSignupEnabled(): bool
    {
        return $this->isEnabled()
            && (bool) $this->settings->get('passkeys.signup_enabled', true)
            && (bool) $this->settings->get('allow_sign_up', true);
    }

    public function getRpName(): string
    {
        $name = trim((string) $this->settings->get('passkeys.rp_name', ''));

        if ($name !== '') {
            return $name;
        }

        $forumTitle = trim((string) $this->settings->get('forum_title', ''));

        return $forumTitle !== '' ? $forumTitle : 'Flarum';
    }

    public function getOrigin(?ServerRequestInterface $request = null): string
    {
        $origin = trim((string) $this->settings->get('passkeys.origin', ''));

        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        if ($request !== null) {
            $uri = $request->getUri();
            $port = $uri->getPort();

            return $uri->getScheme().'://'.$uri->getHost().($port !== null ? ':'.$port : '');
        }

        return 'http://localhost';
    }

    public function getRpId(?ServerRequestInterface $request = null): string
    {
        $rpId = trim((string) $this->settings->get('passkeys.rp_id', ''));

        if ($rpId !== '') {
            return $rpId;
        }

        $host = $request?->getUri()->getHost();

        if ($host === null || $host === '') {
            $host = parse_url($this->getOrigin($request), PHP_URL_HOST);
        }

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    public function getUserHandle(User $user): string
    {
        return pack('N', (int) $user->id);
    }

    /**
     * The user handle that a credential was created with.
     *
     * Credentials created through the account settings use the legacy
     * `getUserHandle()` value. Credentials created during passkey sign-up use
     * a random handle generated before the user row exists, so the handle is
     * stored alongside the credential instead.
     */
    public function getCredentialUserHandle(WebAuthnCredential $credential, User $user): string
    {
        if (is_string($credential->user_handle) && $credential->user_handle !== '') {
            return self::base64UrlDecode($credential->user_handle);
        }

        return $this->getUserHandle($user);
    }

    public function encodeUserHandle(string $handle): string
    {
        return self::base64UrlEncode($handle);
    }

    public function createSerializer(): SerializerInterface
    {
        if ($this->serializer === null) {
            $factory = new WebauthnSerializerFactory(AttestationStatementSupportManager::create());
            $this->serializer = $factory->create();
        }

        return $this->serializer;
    }

    public function serializeOptions(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): array
    {
        return $this->createSerializer()->normalize($options, 'json');
    }

    public function deserializeCreationOptions(array $data): PublicKeyCredentialCreationOptions
    {
        $options = $this->createSerializer()->denormalize($data, PublicKeyCredentialCreationOptions::class, 'json');

        if (! $options instanceof PublicKeyCredentialCreationOptions) {
            throw new \RuntimeException('Invalid registration options.');
        }

        return $options;
    }

    public function deserializeRequestOptions(array $data): PublicKeyCredentialRequestOptions
    {
        $options = $this->createSerializer()->denormalize($data, PublicKeyCredentialRequestOptions::class, 'json');

        if (! $options instanceof PublicKeyCredentialRequestOptions) {
            throw new \RuntimeException('Invalid authentication options.');
        }

        return $options;
    }

    /**
     * @param PublicKeyCredentialDescriptor[] $excludeCredentials
     */
    public function createCreationOptions(User $user, array $excludeCredentials, ServerRequestInterface $request): PublicKeyCredentialCreationOptions
    {
        return $this->createCreationOptionsForIdentity(
            $user->username,
            $user->display_name ?: $user->username,
            $this->getUserHandle($user),
            $excludeCredentials,
            $request
        );
    }

    /**
     * @param PublicKeyCredentialDescriptor[] $excludeCredentials
     */
    public function createCreationOptionsForIdentity(
        string $username,
        string $displayName,
        string $userHandle,
        array $excludeCredentials,
        ServerRequestInterface $request
    ): PublicKeyCredentialCreationOptions {
        $selection = AuthenticatorSelectionCriteria::create(
            null,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
        );

        return PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($this->getRpName(), $this->getRpId($request)),
            PublicKeyCredentialUserEntity::create(
                $username,
                $userHandle,
                $displayName
            ),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::createPk(-7), // ES256
                PublicKeyCredentialParameters::createPk(-257), // RS256
            ],
            $selection,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludeCredentials,
            60000
        );
    }

    public function createRequestOptions(ServerRequestInterface $request): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->getRpId($request),
            [],
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            60000
        );
    }

    public function createAttestationValidator(ServerRequestInterface $request): AuthenticatorAttestationResponseValidator
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([$this->getOrigin($request)]);

        return new AuthenticatorAttestationResponseValidator($factory->creationCeremony());
    }

    public function createAssertionValidator(ServerRequestInterface $request): AuthenticatorAssertionResponseValidator
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([$this->getOrigin($request)]);

        return new AuthenticatorAssertionResponseValidator($factory->requestCeremony());
    }

    public function buildCredentialRecord(WebAuthnCredential $credential, User $user): CredentialRecord
    {
        $aaguid = $credential->aaguid ?: self::NIL_UUID;

        try {
            $aaguid = Uuid::fromString($aaguid);
        } catch (\Throwable) {
            $aaguid = Uuid::fromString(self::NIL_UUID);
        }

        $transports = $credential->transports ? json_decode($credential->transports, true) : [];

        if (! is_array($transports)) {
            $transports = [];
        }

        return CredentialRecord::create(
            self::base64UrlDecode($credential->credential_id),
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            array_values(array_filter($transports, 'is_string')),
            'none',
            EmptyTrustPath::create(),
            $aaguid,
            base64_decode($credential->public_key),
            $this->getCredentialUserHandle($credential, $user),
            (int) $credential->counter
        );
    }

    public function toCredentialDescriptor(WebAuthnCredential $credential): PublicKeyCredentialDescriptor
    {
        $transports = $credential->transports ? json_decode($credential->transports, true) : [];

        if (! is_array($transports)) {
            $transports = [];
        }

        return PublicKeyCredentialDescriptor::create(
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            self::base64UrlDecode($credential->credential_id),
            array_values(array_filter($transports, 'is_string'))
        );
    }

    public function storeOptions(ServerRequestInterface $request, string $key, array $options): void
    {
        $session = $request->getAttribute('session');

        if ($session === null) {
            throw new \RuntimeException('No session available.');
        }

        $session->put('passkeys.'.$key, [
            'options' => $options,
            'expires_at' => time() + self::CHALLENGE_LIFETIME,
        ]);
    }

    public function consumeOptions(ServerRequestInterface $request, string $key): ?array
    {
        $session = $request->getAttribute('session');

        if ($session === null) {
            return null;
        }

        $data = $session->pull('passkeys.'.$key);

        if (! is_array($data) || ! is_array($data['options'] ?? null)) {
            return null;
        }

        if (($data['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $data['options'];
    }

    public function storeSignupData(ServerRequestInterface $request, array $data): void
    {
        $session = $request->getAttribute('session');

        if ($session === null) {
            throw new \RuntimeException('No session available.');
        }

        $session->put('passkeys.signup.data', [
            'data' => $data,
            'expires_at' => time() + self::CHALLENGE_LIFETIME,
        ]);
    }

    public function consumeSignupData(ServerRequestInterface $request): ?array
    {
        $session = $request->getAttribute('session');

        if ($session === null) {
            return null;
        }

        $data = $session->pull('passkeys.signup.data');

        if (! is_array($data) || ! is_array($data['data'] ?? null)) {
            return null;
        }

        if (($data['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $data['data'];
    }

    public static function base64UrlEncode(string $value): string
    {
        return Base64UrlSafe::encodeUnpadded($value);
    }

    public static function base64UrlDecode(string $value): string
    {
        return Base64UrlSafe::decodeNoPadding($value);
    }
}

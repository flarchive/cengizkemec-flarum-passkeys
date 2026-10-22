<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnCredential;
use CengizKemec\Passkeys\WebAuthnService;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredential;

class RegistrationVerifyController implements RequestHandlerInterface
{
    public function __construct(private WebAuthnService $service)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        if (! $this->service->isEnabled()) {
            return new JsonResponse(['error' => 'passkeys_disabled'], 403);
        }

        $body = $request->getParsedBody();

        if (! is_array($body) || ! is_array($body['credential'] ?? null)) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        $storedOptions = $this->service->consumeOptions($request, 'registration.'.$actor->id);

        if ($storedOptions === null) {
            return new JsonResponse(['error' => 'challenge_expired'], 400);
        }

        try {
            $options = $this->service->deserializeCreationOptions($storedOptions);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        if (! hash_equals($options->user->id, $this->service->getUserHandle($actor))) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        try {
            $publicKeyCredential = $this->service->createSerializer()->denormalize(
                $body['credential'],
                PublicKeyCredential::class,
                'json'
            );

            if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
                return new JsonResponse(['error' => 'invalid_request'], 400);
            }

            $record = $this->service->createAttestationValidator($request)->check(
                $publicKeyCredential->response,
                $options,
                $request->getUri()->getHost()
            );
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'verification_failed'], 400);
        }

        $credentialId = WebAuthnService::base64UrlEncode($record->publicKeyCredentialId);

        if (strlen($credentialId) > 255) {
            return new JsonResponse(['error' => 'credential_id_too_long'], 400);
        }

        $existing = WebAuthnCredential::query()->where('credential_id', $credentialId)->first();

        if ($existing !== null && (int) $existing->user_id !== (int) $actor->id) {
            return new JsonResponse(['error' => 'credential_already_registered'], 409);
        }

        $deviceName = $body['device_name'] ?? null;
        $deviceName = is_string($deviceName) ? trim($deviceName) : '';
        $deviceName = function_exists('mb_substr') ? mb_substr($deviceName, 0, 100) : substr($deviceName, 0, 100);

        $credential = $existing ?? new WebAuthnCredential();
        $credential->user_id = $actor->id;
        $credential->credential_id = $credentialId;
        $credential->public_key = $record->credentialPublicKey;
        $credential->counter = (int) $record->counter;
        $credential->aaguid = $record->aaguid->toRfc4122();
        $credential->transports = $record->transports !== [] ? json_encode(array_values($record->transports)) : null;
        $credential->device_name = $deviceName !== '' ? $deviceName : null;
        $credential->save();

        return new JsonResponse([
            'success' => true,
            'credential' => [
                'id' => $credential->id,
                'device_name' => $credential->device_name,
                'created_at' => $credential->created_at,
            ],
        ], 201);
    }
}

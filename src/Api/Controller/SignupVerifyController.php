<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnCredential;
use CengizKemec\Passkeys\WebAuthnService;
use Flarum\Api\Client;
use Flarum\Http\RequestUtil;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\User\Event\LoggedIn;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredential;

class SignupVerifyController implements RequestHandlerInterface
{
    public function __construct(
        private WebAuthnService $service,
        private Client $api,
        private SessionAuthenticator $authenticator,
        private Dispatcher $events
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->service->isSignupEnabled()) {
            return new JsonResponse(['error' => 'signup_disabled'], 403);
        }

        $actor = RequestUtil::getActor($request);

        if (! $actor->isGuest()) {
            return new JsonResponse(['error' => 'already_authenticated'], 403);
        }

        $session = $request->getAttribute('session');

        if ($session === null) {
            return new JsonResponse(['error' => 'session_unavailable'], 500);
        }

        $body = $request->getParsedBody();

        if (! is_array($body) || ! is_array($body['credential'] ?? null)) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        $storedOptions = $this->service->consumeOptions($request, 'signup');

        if ($storedOptions === null) {
            return new JsonResponse(['error' => 'challenge_expired'], 400);
        }

        $signupData = $this->service->consumeSignupData($request);

        if (
            $signupData === null
            || ! is_string($signupData['username'] ?? null)
            || ! is_string($signupData['email'] ?? null)
            || ! is_string($signupData['user_handle'] ?? null)
        ) {
            return new JsonResponse(['error' => 'challenge_expired'], 400);
        }

        try {
            $options = $this->service->deserializeCreationOptions($storedOptions);

            if (! hash_equals($options->user->id, WebAuthnService::base64UrlDecode($signupData['user_handle']))) {
                return new JsonResponse(['error' => 'invalid_request'], 400);
            }

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

        if (WebAuthnCredential::query()->where('credential_id', $credentialId)->exists()) {
            return new JsonResponse(['error' => 'credential_exists'], 409);
        }

        $password = $body['password'] ?? null;

        if (! is_string($password) || $password === '') {
            // A passkey account does not need a password. A random one keeps
            // the account usable with Flarum's password reset flow if the
            // passkey is lost.
            $password = Str::random(40);
        } elseif ((function_exists('mb_strlen') ? mb_strlen($password) : strlen($password)) < 8) {
            return new JsonResponse(['error' => 'password_too_short'], 422);
        }

        $response = $this->api->withParentRequest($request)->withBody([
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'username' => $signupData['username'],
                    'email' => $signupData['email'],
                    'password' => $password,
                ],
            ],
        ])->post('/users');

        $decoded = json_decode((string) $response->getBody(), true);
        $status = $response->getStatusCode();

        if ($status >= 400 || ! is_array($decoded) || ! isset($decoded['data']['id'])) {
            $detail = is_array($decoded['errors'] ?? null) ? ($decoded['errors'][0]['detail'] ?? null) : null;

            return new JsonResponse([
                'error' => 'registration_failed',
                'detail' => is_string($detail) ? $detail : null,
            ], $status >= 400 ? $status : 422);
        }

        $user = User::query()->find((int) $decoded['data']['id']);

        if ($user === null) {
            return new JsonResponse(['error' => 'registration_failed'], 500);
        }

        try {
            $credential = new WebAuthnCredential();
            $credential->user_id = $user->id;
            $credential->user_handle = $signupData['user_handle'];
            $credential->credential_id = $credentialId;
            $credential->public_key = base64_encode($record->credentialPublicKey);
            $credential->counter = (int) $record->counter;
            $credential->aaguid = $record->aaguid->toRfc4122();
            $credential->transports = $record->transports !== [] ? json_encode(array_values($record->transports)) : null;
            $credential->device_name = null;
            $credential->save();
        } catch (\Throwable) {
            try {
                $user->delete();
            } catch (\Throwable) {
                // Best effort cleanup; the registration error is more useful.
            }

            return new JsonResponse(['error' => 'registration_failed'], 500);
        }

        $token = SessionAccessToken::generate((int) $user->id);

        $this->authenticator->logIn($session, $token);
        $this->events->dispatch(new LoggedIn($user, $token));

        return new JsonResponse([
            'success' => true,
            'userId' => (int) $user->id,
        ], 201);
    }
}

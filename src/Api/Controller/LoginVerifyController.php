<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnCredential;
use CengizKemec\Passkeys\WebAuthnService;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\User\Event\LoggedIn;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredential;

class LoginVerifyController implements RequestHandlerInterface
{
    public function __construct(
        private WebAuthnService $service,
        private SessionAuthenticator $authenticator,
        private Dispatcher $events
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->service->isEnabled()) {
            return new JsonResponse(['error' => 'passkeys_disabled'], 403);
        }

        $body = $request->getParsedBody();

        if (! is_array($body) || ! is_array($body['credential'] ?? null)) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        $storedOptions = $this->service->consumeOptions($request, 'login');

        if ($storedOptions === null) {
            return new JsonResponse(['error' => 'challenge_expired'], 400);
        }

        try {
            $options = $this->service->deserializeRequestOptions($storedOptions);

            $publicKeyCredential = $this->service->createSerializer()->denormalize(
                $body['credential'],
                PublicKeyCredential::class,
                'json'
            );

            if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
                return new JsonResponse(['error' => 'invalid_request'], 400);
            }
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        $credentialId = WebAuthnService::base64UrlEncode($publicKeyCredential->rawId);

        $credential = WebAuthnCredential::query()
            ->where('credential_id', $credentialId)
            ->first();

        if ($credential === null) {
            return new JsonResponse(['error' => 'unknown_credential'], 400);
        }

        $user = User::query()->find($credential->user_id);

        if ($user === null) {
            return new JsonResponse(['error' => 'unknown_credential'], 400);
        }

        try {
            $record = $this->service->buildCredentialRecord($credential, $user);

            $updatedRecord = $this->service->createAssertionValidator($request)->check(
                $record,
                $publicKeyCredential->response,
                $options,
                $request->getUri()->getHost(),
                $this->service->getCredentialUserHandle($credential, $user)
            );
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'verification_failed'], 400);
        }

        $credential->counter = (int) $updatedRecord->counter;
        $credential->save();

        $token = SessionAccessToken::generate((int) $user->id);
        $session = $request->getAttribute('session');

        if ($session === null) {
            $token->delete();

            return new JsonResponse(['error' => 'session_unavailable'], 500);
        }

        $this->authenticator->logIn($session, $token);
        $this->events->dispatch(new LoggedIn($user, $token));

        return new JsonResponse([
            'success' => true,
            'userId' => (int) $user->id,
        ]);
    }
}

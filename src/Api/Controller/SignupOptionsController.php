<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnService;
use Flarum\Http\RequestUtil;
use Flarum\User\UserValidator;
use Illuminate\Validation\ValidationException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SignupOptionsController implements RequestHandlerInterface
{
    public function __construct(
        private WebAuthnService $service,
        private UserValidator $users
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

        $body = $request->getParsedBody();
        $username = is_array($body) && is_string($body['username'] ?? null) ? trim($body['username']) : '';
        $email = is_array($body) && is_string($body['email'] ?? null) ? trim($body['email']) : '';

        try {
            $this->users->assertValid([
                'username' => $username,
                'email' => $email,
            ]);
        } catch (ValidationException $error) {
            return new JsonResponse([
                'error' => 'validation_failed',
                'errors' => $error->errors(),
            ], 422);
        }

        // The user row does not exist yet, so use a random handle that will be
        // stored with the credential and reused when the user logs in later.
        $userHandle = random_bytes(32);

        $options = $this->service->createCreationOptionsForIdentity(
            $username,
            $username,
            $userHandle,
            [],
            $request
        );

        $json = $this->service->serializeOptions($options);

        $this->service->storeOptions($request, 'signup', $json);
        $this->service->storeSignupData($request, [
            'username' => $username,
            'email' => $email,
            'user_handle' => WebAuthnService::base64UrlEncode($userHandle),
        ]);

        return new JsonResponse(['publicKey' => $json]);
    }
}

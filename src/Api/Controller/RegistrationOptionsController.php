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

class RegistrationOptionsController implements RequestHandlerInterface
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

        $excludeCredentials = WebAuthnCredential::query()
            ->where('user_id', $actor->id)
            ->get()
            ->map(fn (WebAuthnCredential $credential) => $this->service->toCredentialDescriptor($credential))
            ->all();

        $options = $this->service->createCreationOptions($actor, $excludeCredentials, $request);
        $json = $this->service->serializeOptions($options);

        $this->service->storeOptions($request, 'registration.'.$actor->id, $json);

        return new JsonResponse(['publicKey' => $json]);
    }
}

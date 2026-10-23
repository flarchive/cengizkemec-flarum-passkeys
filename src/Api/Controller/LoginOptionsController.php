<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoginOptionsController implements RequestHandlerInterface
{
    public function __construct(private WebAuthnService $service)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->service->isEnabled()) {
            return new JsonResponse(['error' => 'passkeys_disabled'], 403);
        }

        $options = $this->service->createRequestOptions($request);
        $json = $this->service->serializeOptions($options);

        $this->service->storeOptions($request, 'login', $json);

        return new JsonResponse(['publicKey' => $json]);
    }
}

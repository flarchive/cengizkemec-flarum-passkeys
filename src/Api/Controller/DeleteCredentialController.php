<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnCredential;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteCredentialController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $parameters = $request->getAttribute('routeParameters', []);
        $id = (int) ($parameters['id'] ?? 0);

        $credential = WebAuthnCredential::query()
            ->where('id', $id)
            ->where('user_id', $actor->id)
            ->first();

        if ($credential === null) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        $credential->delete();

        return new JsonResponse(['success' => true]);
    }
}

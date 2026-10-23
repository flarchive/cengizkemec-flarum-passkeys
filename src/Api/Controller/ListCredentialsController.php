<?php

declare(strict_types=1);

namespace CengizKemec\Passkeys\Api\Controller;

use CengizKemec\Passkeys\WebAuthnCredential;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListCredentialsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $credentials = WebAuthnCredential::query()
            ->where('user_id', $actor->id)
            ->orderByDesc('created_at')
            ->get();

        return new JsonResponse([
            'data' => $credentials->map(fn (WebAuthnCredential $credential) => [
                'id' => (int) $credential->id,
                'device_name' => $credential->device_name,
                'created_at' => $credential->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}

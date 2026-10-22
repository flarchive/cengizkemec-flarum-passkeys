<?php

use CengizKemec\Passkeys\Api\Controller\DeleteCredentialController;
use CengizKemec\Passkeys\Api\Controller\ListCredentialsController;
use CengizKemec\Passkeys\Api\Controller\LoginOptionsController;
use CengizKemec\Passkeys\Api\Controller\LoginVerifyController;
use CengizKemec\Passkeys\Api\Controller\RegistrationOptionsController;
use CengizKemec\Passkeys\Api\Controller\RegistrationVerifyController;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('forum'))
        ->post('/passkeys/register/options', 'passkeys.register.options', RegistrationOptionsController::class)
        ->post('/passkeys/register/verify', 'passkeys.register.verify', RegistrationVerifyController::class)
        ->post('/passkeys/login/options', 'passkeys.login.options', LoginOptionsController::class)
        ->post('/passkeys/login/verify', 'passkeys.login.verify', LoginVerifyController::class)
        ->get('/passkeys/credentials', 'passkeys.credentials.index', ListCredentialsController::class)
        ->post('/passkeys/credentials/{id}/delete', 'passkeys.credentials.delete', DeleteCredentialController::class),

    (new Extend\Settings())
        ->default('passkeys.enabled', '1')
        ->default('passkeys.rp_id', '')
        ->default('passkeys.rp_name', 'Flarum')
        ->default('passkeys.origin', '')
        ->serializeToForum('passkeysEnabled', 'passkeys.enabled', function ($value) {
            return (bool) $value;
        }),
];

<?php

namespace CengizKemec\Passkeys;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class WebAuthnCredential extends AbstractModel
{
    protected $table = 'webauthn_credentials';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'user_handle',
        'credential_id',
        'public_key',
        'counter',
        'aaguid',
        'transports',
        'device_name',
    ];

    protected $casts = [
        'counter' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

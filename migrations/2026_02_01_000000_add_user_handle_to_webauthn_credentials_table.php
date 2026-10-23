<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('webauthn_credentials') || $schema->hasColumn('webauthn_credentials', 'user_handle')) {
            return;
        }

        $schema->table('webauthn_credentials', function (Blueprint $table) {
            // Base64url encoded WebAuthn user handle. Nullable so credentials
            // created before this migration keep using the legacy user id
            // handle.
            $table->string('user_handle', 64)->nullable();
        });
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasTable('webauthn_credentials') || ! $schema->hasColumn('webauthn_credentials', 'user_handle')) {
            return;
        }

        $schema->table('webauthn_credentials', function (Blueprint $table) {
            $table->dropColumn('user_handle');
        });
    },
];

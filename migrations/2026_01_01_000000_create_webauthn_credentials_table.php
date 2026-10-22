<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('webauthn_credentials')) {
            return;
        }

        $schema->create('webauthn_credentials', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('credential_id', 255)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('counter')->default(0);
            $table->string('aaguid', 36)->nullable();
            $table->text('transports')->nullable();
            $table->string('device_name', 100)->nullable();
            $table->timestamps();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('webauthn_credentials');
    },
];

<?php

use Flarum\User\User;

return [
    'up' => function () {
        foreach (User::whereNotNull('flagrow_telegram_id')->cursor() as $user) {
            $user->loginProviders()->create([
                'provider' => 'telegram',
                'identifier' => $user->flagrow_telegram_id
            ]);
        }
    },
    'down' => function () {
        // do nothing
    }
];

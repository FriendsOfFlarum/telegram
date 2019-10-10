<?php

namespace Flagrow\Telegram;
use Flagrow\Telegram\Controllers\TelegramAuthController;
use Flagrow\Telegram\Controllers\TelegramBotController;
use Flarum\Extend;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory;
use Flarum\Notification\Event\Sending;
use Flarum\Api\Event\Serializing;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),
    (new Extend\Locales(__DIR__ . '/resources/locale')),
    function (Dispatcher $events, Factory $view) {
        $events->listen(Serializing::class, Listeners\AddUserAttributes::class);
        $events->subscribe(Listeners\SendTelegramNotifications::class);
        $events->subscribe(Listeners\EnableTelegramNotifications::class);
        $events->subscribe(Listeners\InjectSettings::class);

    },
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Routes('forum'))
        ->get('/auth/telegram', 'auth.telegram', TelegramAuthController::class),
];



<?php

namespace Flagrow\Telegram\Listeners;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Event\Serializing;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

class InjectSettings
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(Serializing::class, [$this, 'settings']);
    }

    public function settings(Serializing $event)
    {
        if ($event->serializer instanceof ForumSerializer) {
            $event->attributes['flagrow-telegram.enableNotifications'] = (bool)$this->settings->get('flagrow-telegram.enableNotifications');
            $event->attributes['flagrow-telegram.botUsername'] = $this->settings->get('flagrow-telegram.botUsername');
        }
    }
}

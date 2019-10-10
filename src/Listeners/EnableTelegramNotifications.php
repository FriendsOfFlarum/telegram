<?php

namespace Flagrow\Telegram\Listeners;

use Flarum\User\User;
use Flarum\Event\ConfigureNotificationTypes;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

class EnableTelegramNotifications
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(ConfigureNotificationTypes::class, [$this, 'configure']);
    }

    public function configure(ConfigureNotificationTypes $event)
    {
        if (!$this->settings->get('flagrow-telegram.enableNotifications')) {
            return;
        }

        // Currently we can only use notifications implementing MailableInterface because we re-use the mail view
        $telegramCompatibleTypes = [
            'postMentioned',
            'userMentioned',
            'newPost',
            'newDiscussionInTag',
            'newPostInTag',
            'discussionCreated', // for fof/subscribed extension
            'userCreated', // for fof/subscribed extension
        ];

        foreach ($telegramCompatibleTypes as $type) {
            User::addPreference(
                User::getNotificationPreferenceKey($type, 'telegram'),
                'boolval',
                false
            );
        }
    }
}

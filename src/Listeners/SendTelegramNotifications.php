<?php

namespace Flagrow\Telegram\Listeners;

use Flagrow\Telegram\Notifications\TelegramMailer;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use Flarum\Notification\Event\Sending;
use Illuminate\Contracts\Events\Dispatcher;
use Flarum\User\LoginProvider;

class SendTelegramNotifications
{
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Sending::class, [$this, 'send']);
    }

    public function send(Sending $event)
    {
        /**
         * @var $mailer TelegramMailer
         */
        $mailer = app(TelegramMailer::class);
        foreach ($event->users as $user) {
            $telegram_id = $this->shouldSendTelegramToUser($event->blueprint, $user);
            if ($telegram_id) {
                $mailer->send($event->blueprint, $user, $telegram_id);
            }
        }
    }

    protected function shouldSendTelegramToUser($blueprint, User $user)
    {
        if (!$user->getPreference(User::getNotificationPreferenceKey($blueprint::getType(), 'telegram'))) {
            return false;
        }
        return $this->getTelegramId($user);
    }

    protected function getTelegramId(User $actor)
    {
        $provider = $actor->LoginProviders()->where('provider', '=', 'telegram')->first();
        return $provider->identifier;
    }
}

<?php

namespace Flagrow\Telegram\Notifications;

use Exception;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\View\Factory;

class TelegramMailer
{
    protected $client;
    protected $views;

    public function __construct(SettingsRepositoryInterface $settings, Factory $views)
    {
        $token = $settings->get('flagrow-telegram.botToken');

        if (!$token) {
            throw new Exception('No bot token configured for Telegram');
        }

        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org/bot' . $token . '/',
        ]);

        $this->views = $views;
    }

    public function send(BlueprintInterface $blueprint, User $user, $telegram_id)
    {
        if ($blueprint instanceof MailableInterface) {
            $view = $this->pickBestView($blueprint->getEmailView());

            $text = $this->views->make($view, compact('blueprint', 'user'))->render();
        } else {
            throw new Exception('Notification not compatible with Telegram Mailer');
        }

        try {
            $this->client->post('sendMessage', [
                'query' => [
                    'chat_id' => (int)$telegram_id,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ],
            ]);

            // Reset error if everything went right
            if ($user->flagrow_telegram_error) {
                $user->flagrow_telegram_error = null;
                $user->save();
            }
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if ($response->getStatusCode() !== 403) {
                throw $exception;
            }

            $user->flagrow_telegram_error = 'unauthorized';

            $json = json_decode($response->getBody()->getContents(), true);

            if ($json && str_contains(array_get($json, 'description', ''), 'blocked by the user')) {
                $user->flagrow_telegram_error = 'blocked';
            }

            $user->save();
        }
    }

    /**
     * Read the same way as Illuminate\Mail\Mailer::parseView()
     * @param $view
     * @return string
     * @throws Exception
     */
    protected function pickBestView($view)
    {
        if (is_string($view)) {
            return $view;
        }

        if (is_array($view)) {
            if (isset($view[0])) {
                return $view[0];
            }

            $html = array_get($view, 'html');

            if ($html) {
                return $html;
            }

            $text = array_get($view, 'text');

            if ($text) {
                return $text;
            }

            $raw = array_get($view, 'raw');

            if ($raw) {
                return $raw;
            }
        }

        throw new Exception('No view found for that mailable');
    }
}

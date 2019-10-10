<?php

namespace Flagrow\Telegram\Controllers;

use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;
use Zend\Diactoros\Response\HtmlResponse;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Flarum\User\LoginProvider;

class TelegramAuthController implements RequestHandlerInterface
{
    protected $authResponse;
    protected $settings;
    protected $url;
    protected $client;

    public function __construct(ResponseFactory $authResponse, SettingsRepositoryInterface $settings, UrlGenerator $url)
    {
        $this->authResponse = $authResponse;
        $this->settings = $settings;
        $this->url = $url;

        $token = $settings->get('flagrow-telegram.botToken');

        if (!$token) {
            throw new Exception('No bot token configured for Telegram');
        }

        $this->client = new Client([
            'base_uri' => 'https://api.telegram.org/bot' . $token . '/',
        ]);
    }

    public function handle(Request $request): ResponseInterface
    {
        $provider = 'telegram';
        $auth = $request->getQueryParams();

        if (!array_key_exists('hash', $auth)) {
            $settings = [
                'telegram-login' => $this->settings->get('flagrow-telegram.botUsername'),
                'size' => 'large',
                'auth-url' => $this->url->to('auth.telegram'),
            ];

            if ($this->settings->get('flagrow-telegram.enableNotifications')) {
                $settings['request-access'] = 'write';
            }

            // The Telegram login system is very much non-standard.
            // Many things make it impractical to directly include in the login modal:
            // - Running the provided javascript creates a login button at the place of the script tag
            // - The script creates its own modal
            // - The callback url is not used in a redirect but is applied to the current page by the javascript
            // The safest at this point is to run the provided javascript inside the Flarum auth modal
            // A second modal will open to authorize the Telegram account,
            // then the script will return to the first modal which will be redirected to the callback url,
            // which will then return the login credentials to the main app as any other auth provider.
            return new HtmlResponse('<!DOCTYPE HTML><body style="text-align:center;padding:100px 20px;"><script async src="https://telegram.org/js/telegram-widget.js?2" '
                . implode(' ', array_map(function ($key, $value) {
                    return 'data-' . $key . '="' . htmlspecialchars($value) . '"';
                }, array_keys($settings), $settings))
                . '></script></body>');
        }

        $this->checkTelegramAuthorization($auth);

        $user = $request->getAttribute('actor');
        if ($user && $user->id) {
            $identifier = array_get($auth, 'id');
            // var_dump($this->checkTelegramId($identifier));exit(1);
            if ($this->checkTelegramId($provider, $identifier)) {
                $content = '<div style="text-align:center;font-family:Arial;">You can\'t link this telegram account to this user.</div>';
                return new HtmlResponse($content);
            }
            $user->loginProviders()->create(compact('provider', 'identifier'));
            $content = '<script>window.close();window.opener.document.location.reload(true);</script>';

            return new HtmlResponse($content);
        }

        $suggestions = [];
        if (array_get($auth, 'username')) $suggestions['username'] = array_get($auth, 'username');
        if (array_get($auth, 'photo_url')) $suggestions['avatarUrl'] = array_get($auth, 'photo_url');


        return $this->authResponse->make(
            $provider, array_get($auth, 'id'),
            function (Registration $registration) use ($suggestions) {
                if ($suggestions['username']) {
                    $registration->suggestUsername($suggestions['username']);
                }
                $registration->setPayload($suggestions);
            }
        );
    }

    protected function checkTelegramId($provider, $identifier)
    {
        $provider = LoginProvider::where(compact('provider', 'identifier'))->first();
        return $provider;
    }

    /**
     * Based on https://gist.github.com/anonymous/6516521b1fb3b464534fbc30ea3573c2
     * @param array $auth_data
     * @throws Exception
     */
    protected function checkTelegramAuthorization(array $auth_data)
    {
        if (!array_key_exists('hash', $auth_data)) {
            throw new Exception('Hash missing');
        }

        $check_hash = $auth_data['hash'];
        unset($auth_data['hash']);
        $data_check_arr = [];

        foreach ($auth_data as $key => $value) {
            $data_check_arr[] = $key . '=' . $value;
        }

        sort($data_check_arr);
        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', $this->settings->get('flagrow-telegram.botToken'), true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        if (strcmp($hash, $check_hash) !== 0) {
            throw new Exception('Data is NOT from Telegram');
        }

        if (!array_key_exists('auth_date', $auth_data)) {
            throw new Exception('Auth date missing');
        }

        if ((time() - $auth_data['auth_date']) > 86400) {
            throw new Exception('Data is outdated');
        }
    }
}

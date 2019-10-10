import {extend} from 'flarum/extend';
import app from 'flarum/app';
import NotificationGrid from 'flarum/components/NotificationGrid';
import SettingsPage from 'flarum/components/SettingsPage';
import LogInButton from 'flarum/components/LogInButton';

export default function () {
    extend(NotificationGrid.prototype, 'notificationMethods', function (items) {
        if (!app.forum.attribute('flagrow-telegram.enableNotifications')) {
            return;
        }

        let user = app.session.user;
        if (!user || !user.canReceiveTelegramNotifications()) {
            return;
        }

        // Add telegram notifications method column
        items.add('telegram', {
            name: 'telegram',
            icon: 'fab fa-telegram-plane',
            label: app.translator.trans('flagrow-telegram.forum.settings.notify_by_telegram_heading'),
        });
    });
    extend(SettingsPage.prototype, 'accountItems', function (items) {
        if (!app.forum.attribute('flagrow-telegram.enableNotifications')) {
            return;
        }
        let user = app.session.user;
        if (user && !user.canReceiveTelegramNotifications()) {
            // add button to link current account with telegram
            items.add('flagrow-telegram',
                <div>
                    <LogInButton
                        className="Button LogInButton--telegram"
                        style="display:inline-block; width:auto"
                        icon="fab fa-telegram-plane"
                        path="/auth/telegram">
                        {app.translator.trans('flagrow-telegram.forum.link_telegram_button')}
                    </LogInButton>
                </div>
            );
        }
    });
    extend(SettingsPage.prototype, 'notificationsItems', function (items) {

        if (!app.forum.attribute('flagrow-telegram.enableNotifications')) {
            return;
        }

        let user = app.session.user;
        if (!user || !user.flagrowTelegramError()) {
            return;
        }

        const botUsername = app.forum.attribute('flagrow-telegram.botUsername');

        items.add('flagrowTelegramError', {
            view() {
                return m('.Alert', m('p', app.translator.trans('flagrow-telegram.forum.settings.unblock_telegram_bot', {
                    a: m('a', {href: 'https://t.me/' + botUsername}),
                    username: '@' + botUsername,
                })))
            },
        });
    });
}



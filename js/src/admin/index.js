import app from 'flarum/app';

import TelegramSettingsModal from './components/TelegramSettingsModal';

app.initializers.add('flagrow-telegram', () => {
  app.extensionSettings['flagrow-telegram'] = () => app.modal.show(new TelegramSettingsModal());
});

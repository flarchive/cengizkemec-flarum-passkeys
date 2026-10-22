import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LogInModal from 'flarum/forum/components/LogInModal';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import Button from 'flarum/common/components/Button';
import PasskeysSettings from './components/PasskeysSettings';
import { decodeRequestOptions, serializeAssertionCredential, isUserCancellation } from './utils/webauthn';

export { default as extend } from './extend';

async function loginWithPasskey(): Promise<void> {
  if (!app.forum.attribute('passkeysEnabled')) return;

  try {
    const response = await app.request<any>({
      method: 'POST',
      url: app.forum.attribute('baseUrl') + '/passkeys/login/options',
      errorHandler: () => {},
    });

    const publicKey = decodeRequestOptions(response.publicKey);
    const credential = (await navigator.credentials.get({ publicKey })) as PublicKeyCredential | null;

    if (!credential) {
      return;
    }

    await app.request({
      method: 'POST',
      url: app.forum.attribute('baseUrl') + '/passkeys/login/verify',
      body: { credential: serializeAssertionCredential(credential) },
      errorHandler: () => {},
    });

    window.location.reload();
  } catch (error) {
    if (!isUserCancellation(error)) {
      app.alerts.show({ type: 'error' }, app.translator.trans('cengizkemec-passkeys.forum.log_in.passkey_error'));
    }
  }
}

app.initializers.add('cengizkemec-passkeys', () => {
  extend(LogInModal.prototype, 'fields', function (items) {
    if (!app.forum.attribute('passkeysEnabled')) return;

    items.add(
      'passkey-login',
      <div className="Form-group">
        <Button className="Button Button--block PasskeyLoginButton" icon="fas fa-key" onclick={() => loginWithPasskey()}>
          {app.translator.trans('cengizkemec-passkeys.forum.log_in.with_passkey')}
        </Button>
      </div>,
      5
    );
  });

  extend(SettingsPage.prototype, 'accountItems', function (items) {
    if (!app.session.user || !app.forum.attribute('passkeysEnabled')) return;

    items.add('passkeys', <PasskeysSettings />, 50);
  });
});

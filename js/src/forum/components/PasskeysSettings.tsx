import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import {
  decodeCreationOptions,
  serializeRegistrationCredential,
  isUserCancellation,
} from '../utils/webauthn';

interface CredentialSummary {
  id: number;
  device_name: string | null;
  created_at: string | null;
}

export default class PasskeysSettings extends Component {
  loading = false;
  credentials: CredentialSummary[] = [];

  oninit(): void {
    this.load();
  }

  async load(): Promise<void> {
    this.loading = true;

    try {
      const response = await app.request<any>({
        method: 'GET',
        url: app.forum.attribute('baseUrl') + '/passkeys/credentials',
        errorHandler: () => {},
      });

      this.credentials = response.data || [];
    } catch {
      this.credentials = [];
    }

    this.loading = false;
    m.redraw();
  }

  async register(): Promise<void> {
    if (!app.forum.attribute('passkeysEnabled')) return;

    this.loading = true;

    try {
      const response = await app.request<any>({
        method: 'POST',
        url: app.forum.attribute('baseUrl') + '/passkeys/register/options',
        errorHandler: () => {},
      });

      const publicKey = decodeCreationOptions(response.publicKey);
      const credential = (await navigator.credentials.create({ publicKey })) as PublicKeyCredential | null;

      if (!credential) {
        return;
      }

      const deviceName = window.prompt(extractText(app.translator.trans('cengizkemec-passkeys.forum.settings.device_name_prompt')));

      if (deviceName === null) {
        return;
      }

      await app.request({
        method: 'POST',
        url: app.forum.attribute('baseUrl') + '/passkeys/register/verify',
        body: {
          credential: serializeRegistrationCredential(credential),
          device_name: deviceName,
        },
        errorHandler: () => {},
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('cengizkemec-passkeys.forum.settings.added'));
      await this.load();
    } catch (error) {
      if (!isUserCancellation(error)) {
        app.alerts.show({ type: 'error' }, app.translator.trans('cengizkemec-passkeys.forum.settings.error'));
      }
    } finally {
      this.loading = false;
    }
  }

  async deleteCredential(id: number): Promise<void> {
    if (!window.confirm(extractText(app.translator.trans('cengizkemec-passkeys.forum.settings.delete_confirm')))) return;

    this.loading = true;

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('baseUrl') + '/passkeys/credentials/' + id + '/delete',
        errorHandler: () => {},
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('cengizkemec-passkeys.forum.settings.deleted'));
      await this.load();
    } catch {
      app.alerts.show({ type: 'error' }, app.translator.trans('cengizkemec-passkeys.forum.settings.error'));
    } finally {
      this.loading = false;
    }
  }

  view(): Mithril.Children {
    return (
      <fieldset className="PasskeysSettings SettingsWidget">
        <legend>{app.translator.trans('cengizkemec-passkeys.forum.settings.passkeys_heading')}</legend>

        {this.loading ? (
          <LoadingIndicator />
        ) : (
          <div>
            <ul className="PasskeysList">
              {this.credentials.length === 0 && (
                <li className="PasskeysList-item PasskeysList-item--empty">
                  {app.translator.trans('cengizkemec-passkeys.forum.settings.no_passkeys')}
                </li>
              )}

              {this.credentials.map((credential) => (
                <li className="PasskeysList-item" key={credential.id}>
                  <span className="PasskeysList-name">
                    <i className="fas fa-key" aria-hidden="true" /> {credential.device_name || app.translator.trans('cengizkemec-passkeys.forum.settings.unnamed_device')}
                  </span>
                  <Button
                    className="Button Button--danger Button--text"
                    icon="fas fa-trash-alt"
                    onclick={() => this.deleteCredential(credential.id)}
                  >
                    {app.translator.trans('cengizkemec-passkeys.forum.settings.delete')}
                  </Button>
                </li>
              ))}
            </ul>

            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.register()}>
              {app.translator.trans('cengizkemec-passkeys.forum.settings.add_passkey')}
            </Button>
          </div>
        )}
      </fieldset>
    );
  }
}

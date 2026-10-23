import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type SignUpModal from 'flarum/forum/components/SignUpModal';
import type Mithril from 'mithril';
import { decodeCreationOptions, isUserCancellation, serializeRegistrationCredential } from '../utils/webauthn';

export interface PasskeySignUpButtonAttrs {
  modal: SignUpModal;
}

export default class PasskeySignUpButton extends Component<PasskeySignUpButtonAttrs> {
  loading = false;

  protected errorMessage(error: unknown): Mithril.Children {
    const requestError = error as {
      response?: {
        error?: string;
        detail?: unknown;
        errors?: Array<{ detail?: string }> | Record<string, string[]>;
      };
    };

    if (typeof requestError.response?.detail === 'string' && requestError.response.detail !== '') {
      return requestError.response.detail;
    }

    const errors = requestError.response?.errors;

    if (Array.isArray(errors)) {
      const detail = errors[0]?.detail;

      if (typeof detail === 'string' && detail !== '') {
        return detail;
      }
    } else if (errors && typeof errors === 'object') {
      for (const messages of Object.values(errors)) {
        if (Array.isArray(messages) && typeof messages[0] === 'string' && messages[0] !== '') {
          return messages[0];
        }
      }
    }

    switch (requestError.response?.error) {
      case 'signup_disabled':
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.signup_disabled');
      case 'already_authenticated':
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.already_authenticated');
      case 'challenge_expired':
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.session_expired');
      case 'credential_exists':
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.credential_exists');
      case 'password_too_short':
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.password_too_short');
      case 'registration_failed':
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.registration_failed');
      default:
        return app.translator.trans('cengizkemec-passkeys.forum.sign_up.error');
    }
  }

  async signUp(): Promise<void> {
    if (this.loading) return;

    const username = this.attrs.modal.username().trim();
    const email = this.attrs.modal.email().trim();
    const password = this.attrs.modal.password();

    if (!username || !email) {
      app.alerts.show({ type: 'error' }, app.translator.trans('cengizkemec-passkeys.forum.sign_up.missing_fields'));
      return;
    }

    if (password !== '' && password.length < 8) {
      app.alerts.show({ type: 'error' }, app.translator.trans('cengizkemec-passkeys.forum.sign_up.password_too_short'));
      return;
    }

    this.loading = true;
    m.redraw();

    try {
      const response = await app.request<any>({
        method: 'POST',
        url: app.forum.attribute('baseUrl') + '/passkeys/signup/options',
        body: { username, email },
        errorHandler: () => {},
      });

      const publicKey = decodeCreationOptions(response.publicKey);
      const credential = (await navigator.credentials.create({ publicKey })) as PublicKeyCredential | null;

      if (!credential) {
        return;
      }

      const body: Record<string, unknown> = {
        credential: serializeRegistrationCredential(credential),
      };

      if (password !== '') {
        body.password = password;
      }

      await app.request({
        method: 'POST',
        url: app.forum.attribute('baseUrl') + '/passkeys/signup/verify',
        body,
        errorHandler: () => {},
      });

      window.location.reload();
    } catch (error) {
      if (!isUserCancellation(error)) {
        app.alerts.show({ type: 'error' }, this.errorMessage(error));
      }
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  view(): Mithril.Children {
    return (
      <div className="Form-group PasskeySignUp">
        <Button
          className="Button Button--block PasskeySignUpButton"
          icon="fas fa-key"
          loading={this.loading}
          onclick={() => this.signUp()}
        >
          {app.translator.trans('cengizkemec-passkeys.forum.sign_up.with_passkey')}
        </Button>
        <p className="helpText">{app.translator.trans('cengizkemec-passkeys.forum.sign_up.passkey_help')}</p>
      </div>
    );
  }
}

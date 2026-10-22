# Passkeys for Flarum

Passkey (WebAuthn/FIDO2) authentication for Flarum 2.0. Users can register passkeys from their account settings and sign in without a username or password.

Biometric data never leaves the user's device. The server only receives and stores public keys, credential IDs and signature counters.

## Features

- Passkey registration from the account settings page
- Usernameless (discoverable credential) login from the login modal
- Credential list and removal from account settings
- Admin settings for enabling the feature and configuring the Relying Party
- English and Turkish translations
- Session-based endpoints protected by Flarum's CSRF middleware
- One-time, expiring challenge storage (5 minutes)
- User verification and resident keys required
- No biometric data is ever sent to the server

## Requirements

- Flarum 2.0+
- PHP 8.3+
- HTTPS in production (`localhost` is accepted by browsers for development)
- `web-auth/webauthn-lib` `^5.3` (the range also allows a future 5.4 release)

## Installation

The package is available on [Packagist](https://packagist.org/packages/cengizkemec/passkeys):

```bash
composer require cengizkemec/passkeys:^1.0.0
```

Then enable **Passkeys** in the Flarum admin panel.

Compiled JavaScript files are committed in `js/dist`, so no frontend build is required to use the extension.

## Frontend development

```bash
cd js
npm install
npm run build
```

For a development build with file watching:

```bash
cd js
npm run dev
```

## Admin settings

| Setting | Description | Default |
| --- | --- | --- |
| Enable Passkeys | Turns passkey registration and login on or off. | Enabled |
| Relying Party ID (RP ID) | The domain the passkeys belong to, for example `example.com`. | Current request host |
| Relying Party Name | Name shown by the browser when creating a passkey. | `Flarum`, or the forum title |
| Origin | Full origin including scheme, for example `https://example.com`. | Current request origin |

For production, set the RP ID and origin explicitly:

- RP ID: `example.com`
- Origin: `https://example.com`

If the forum runs on a subdomain, the RP ID may be the registrable parent domain (for example `example.com` for `forum.example.com`) if you want the passkey to be usable across subdomains. The origin must always be the exact origin of the forum.

## How it works

1. **Registration**
   - The server creates a WebAuthn creation challenge and stores it in the session.
   - The browser asks the authenticator (Touch ID, Windows Hello, security key, etc.) to create a credential.
   - The server validates the attestation response, stores the public key and credential ID, and never receives biometric data.
2. **Login**
   - The server creates a discoverable-credential request challenge.
   - The browser lets the user choose a passkey.
   - The server looks up the credential ID, validates the assertion signature and counter, then creates a Flarum session.
3. **Credential management**
   - Users can list and remove their own passkeys from account settings.

Challenges are stored in the session, expire after five minutes, and are removed after a single verification attempt.

## Endpoints

All routes are registered on the forum frontend and use Flarum's session and CSRF handling.

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/passkeys/register/options` | Create registration options |
| `POST` | `/passkeys/register/verify` | Verify and store a new credential |
| `POST` | `/passkeys/login/options` | Create login options |
| `POST` | `/passkeys/login/verify` | Verify an assertion and log in |
| `GET` | `/passkeys/credentials` | List the current user's credentials |
| `POST` | `/passkeys/credentials/{id}/delete` | Delete one of the current user's credentials |

## Security notes

- A successful login regenerates the Flarum session through `SessionAuthenticator`.
- Every credential operation is scoped to the authenticated user.
- The RP ID and origin should be configured explicitly in production.
- Challenge data is one-time use and expires after five minutes.
- Attestation conveyance is set to `none`; no attestation certificate chain is requested or stored.
- Passkey login requires user verification (biometric/PIN) when supported by the authenticator.

## Troubleshooting

- **The passkey button does not appear:** make sure the extension is enabled and clear the Flarum cache (`php flarum cache:clear`).
- **The browser rejects the origin:** verify that the Origin setting exactly matches the address in the browser, including `https://` and any non-standard port.
- **Registration fails on a local network IP:** WebAuthn requires HTTPS or `localhost`. Plain HTTP on an IP address is not a secure context.
- **No passkey prompt appears:** the browser or device may not support WebAuthn, or the user may have cancelled the prompt.

## License

MIT. See [LICENSE](LICENSE).

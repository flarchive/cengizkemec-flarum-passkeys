# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-10-23

- Added passkey-based account creation from the sign-up modal.
- Added an admin setting to enable or disable passkey sign-up.
- Passkey sign-up can optionally use the password entered in the form; otherwise a random password is generated so the account can be recovered through the password reset flow.
- Credentials now store the WebAuthn user handle, which allows passkey sign-up before a user ID exists. Existing credentials keep working through the legacy handle.

## [1.0.0] - 2026-10-22

First stable release.

- Production-ready passkey registration and usernameless login.
- Verified WebAuthn registration and assertion flows, including invalid signature and user-handle rejection.
- Credential management, admin settings, locales, and compiled frontend assets.
- Published on Packagist with automatic GitHub updates.

## [0.1.0] - 2026-10-22

Initial public release.

- Passkey registration with resident-key and user-verification requirements.
- Usernameless passkey login with discoverable credentials.
- Credential listing and deletion from account settings.
- Admin settings for enable/disable, RP ID, RP name, and origin.
- One-time session challenges with a five-minute expiry.
- English and Turkish translations.
- Flarum 2.0 compatibility and compiled frontend assets.

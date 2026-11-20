# Google Login

Version `1.0.2`

Google Login is an independent feature plugin for the Art INPA platform. It adds Google OpenID Connect as the primary option on the existing login page and preserves every classic email/password feature.

## Configuration

1. Install and activate `google-login.zip`.
2. Open **Google Login** in the System admin section.
3. In Google Cloud, create a Web OAuth client and add the exact callback URL shown by the plugin.
4. Save the Client ID and Client Secret.
5. Choose whether verified new Google emails may create local accounts, then enable the plugin.

The client secret is encrypted with the platform application key. It is never rendered back into the settings page. Google access and refresh tokens are not stored.

Google sign-in accepts only an identity whose email claim Google has verified. A new or matching local account is therefore marked verified immediately and does not enter the platform email-code activation flow. Classic registration continues to follow the platform email-verification setting.

## Security

- Cryptographically random OAuth state and OpenID nonce values.
- Ten-minute authorization window.
- Google RSA (`RS256`) signature verification using cached Google certificates.
- Issuer, audience, authorized-party, expiry, issued-at, nonce, verified-email, and optional Workspace-domain validation.
- Rate-limited public OAuth routes.
- Existing local accounts are linked only after Google verifies the same email address.
- Disabling the plugin removes its login view override and Google routes immediately; classic login remains untouched.

## Uninstall

Uninstall removes only `google_login_accounts` and `google_login_settings`. Platform users remain in the `users` table and continue to use classic authentication.

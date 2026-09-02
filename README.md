# j_plg_hrz_disablelogin

This plugin prevents users from logging in via the frontend. It blocks access to `com_users` (e.g. `?option=com_users`, `component/users`) unless a configured secret key is provided. Use this in combination with hiding all login forms and login pages.

**Compatibility:** Joomla 4.2 – 6.x, PHP 7.2.5+.

## Configuration

1. After installation and enabling of this plugin, go to `Extensions -> Plugins -> System - Disable Login`.
2. Enter your desired `Secret Key`. Use a long, random, hard-to-guess value (20+ characters) - this key is the only thing standing between visitors and the login form.
3. Optional: Set a `Target Address` where a visitor should be redirected to if they try to access a blocked address (default: Home page).
4. **Make sure you have no other login pages left!** If you have a login page, e.g. *https://myDomain.tld/login*, one will still be able to log in there because this plugin only checks requests that go through *com_users* (a core component which is always needed for obvious reasons) - it does not block third-party login forms/modules that bypass `com_users`.
5. After saving, one will not be able to log in via the frontend anymore.
6. To log in yourself, use `https://myDomain.tld/my/path/to/joomla?option=com_users&view=login&YOUR_SECRET_KEY` or `https://myDomain.tld/my/path/to/joomla/index.php/component/users?view=login&YOUR_SECRET_KEY`. Access is remembered for the current session (see `Keep access unlocked for the whole session` below to change that).

If you also want to prevent unauthorized access to the backend, you might want to have a look at [plg_hrz_block_access](https://github.com/alve89/j_plg_hrz_block_access).

## Hardening options

These live under the plugin's `Hardening` tab:

- **Keep access unlocked for the whole session** (default: yes) - if disabled, the secret key must be provided with *every* request instead of just once per session.
- **Also block the Web Services API** (default: no) - Joomla's REST API (`/api`) can authenticate independently of the frontend login form (e.g. via HTTP Basic Auth) and is therefore *not* covered by this plugin unless enabled here. Enabling this blocks the entire API for anyone without the secret key or an allow-listed IP. Only enable this if you don't rely on the API for anything else.
- **Always-allowed IP addresses** (default: empty) - one IP address or CIDR range per line (e.g. `203.0.113.4` or `198.51.100.0/24`) that bypasses this plugin entirely. Useful for an office or VPN address.
- **Enable Logging** (default: no) - logs checked addresses and their block status to `plg_hrz_disablelogin.log.php` in Joomla's log directory. The configured secret is redacted from logged URLs.

Changing the configured secret key automatically invalidates any previously unlocked sessions.

## Upgrading from a version prior to 1.1.0

No action needed - a session that was already unlocked under an older version is migrated automatically on the next request.

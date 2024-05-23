# j_plg_hrz_disablelogin
This plugin prevents users to login in the frontend. It blocks access to direct addresses like `?option=com_users` and `component/users`. Use this in combination with hiding all login forms and login pages.

## Configuration
1. After installation and enabling of this plugins, go to `Extensions -> Plugins -> System - Disable Login`
2. Enter your desired `Secret Key`.
3. Optional: Set a `Target Address` where a visitor should be redirected to if they try to access a blocked address (default: Home page).
4. **Make sure you have no login sites anymore!** If you have a login site, let's say *https://myDomain.tld/login*, one will still be able to login there because this plugin only checks the "secret addresses" which are used by the system and which are always enabled (because *com_users* is a core component which is always needed for obvious reasons). 
5. After saving one will not be able to login in frontend anymore.
6. If you still want to login in frontend, you can use either `https://myDomain.tld/my/path/to/joomla?option=com_users&view=login&YOUR_SECRET_KEY` or `https://myDomain.tld/my/path/to/joomla/index.php/component/users?view=login&YOUR_SECRET_KEY`.

If you also want to prevent unauthorized access to the backend, you might want to have a look at [plg_hrz_block_access](https://github.com/alve89/j_plg_hrz_block_access)

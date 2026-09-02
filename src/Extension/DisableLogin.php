<?php

/**
 * @package     plg_system_disablelogin
 * @copyright   (c) 2021 Stefan Herzog
 * @license     GNU/GPL, http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Joomla\Plugin\System\DisableLogin\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Ip\IpHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin to disable frontend access to com_users (and, optionally, the Web Services
 * API) unless a secret key has been provided.
 */
final class DisableLogin extends CMSPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation.
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Namespaced session key used to remember successful access.
     *
     * The stored value is a SHA-256 digest of the configured secret. Changing the
     * secret therefore invalidates previously authorised sessions automatically.
     */
    private const SESSION_KEY = 'plg_system_disablelogin.access_hash';

    /**
     * Session key used by plugin versions prior to 1.1.0.
     */
    private const LEGACY_SESSION_KEY = 'enablelogin';

    /**
     * Prevent adding the same logger repeatedly during one request.
     *
     * @var boolean
     */
    private static $loggerRegistered = false;

    /**
     * Returns the events this subscriber listens to.
     *
     * onAfterRoute (rather than onAfterInitialise) is used for the frontend check so that
     * the routed "option" is reliably available for both SEF and non-SEF URLs - at
     * onAfterInitialise the router has not resolved friendly URLs yet, which would let a
     * request such as /login slip through unblocked. onAfterInitialise is used only for the
     * optional Web Services API block, which does not depend on routing.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterRoute'      => 'onAfterRoute',
        ];
    }

    /**
     * Blocks Web Services API requests when the corresponding option is enabled.
     *
     * @return void
     */
    public function onAfterInitialise(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('api') || !(bool) $this->params->get('blockApi', 0)) {
            return;
        }

        $secretKey = trim((string) $this->params->get('secretKey', ''));

        if ($secretKey === '') {
            return;
        }

        if ($this->isAllowedIp($this->getClientIp())) {
            return;
        }

        if ($this->requestContainsValidSecret($secretKey)) {
            return;
        }

        $this->blockApiRequest($secretKey);
    }

    /**
     * Blocks frontend com_users requests unless access has been granted for this session.
     *
     * @return  void
     */
    public function onAfterRoute(): void
    {
        $app = $this->getApplication();

        // Never interfere with administrator, API or console applications.
        if (!$app->isClient('site')) {
            return;
        }

        $secretKey = trim((string) $this->params->get('secretKey', ''));

        // Keep the historic fail-open behaviour to prevent administrators from
        // accidentally locking out all frontend user functions after installation.
        if ($secretKey === '') {
            $app->enqueueMessage(Text::_('PLG_HRZ_DISABLELOGIN_MESSAGE_WARNING_NO_SECRET'), 'warning');

            return;
        }

        // Requests from an always-allowed IP address / CIDR range bypass the plugin entirely.
        if ($this->isAllowedIp($this->getClientIp())) {
            return;
        }

        $persistUnlock = (bool) $this->params->get('persistUnlock', 1);

        if ($persistUnlock && $this->hasSessionAccess($secretKey)) {
            return;
        }

        if ($this->requestContainsValidSecret($secretKey)) {
            if ($persistUnlock) {
                $this->grantSessionAccess($secretKey);
            }

            return;
        }

        $option = $app->getInput()->getCmd('option', '');

        if ($option === 'com_users') {
            $this->blockRequest($secretKey);

            return;
        }

        if ((int) $this->params->get('enableLogging', 0) === 1) {
            $this->logAddress(false, $secretKey);
        }
    }

    /**
     * Checks whether this session has already been authorised for the current secret.
     *
     * @param   string  $secretKey  Configured secret.
     *
     * @return boolean
     */
    private function hasSessionAccess(string $secretKey): bool
    {
        $session      = $this->getApplication()->getSession();
        $expectedHash = hash('sha256', $secretKey);
        $storedHash   = (string) $session->get(self::SESSION_KEY, '');

        if ($storedHash !== '' && hash_equals($expectedHash, $storedHash)) {
            return true;
        }

        // Seamlessly migrate an already authorised session from versions < 1.1.0.
        if ((bool) $session->get(self::LEGACY_SESSION_KEY, false)) {
            $session->set(self::SESSION_KEY, $expectedHash);
            $session->set(self::LEGACY_SESSION_KEY, false);

            return true;
        }

        return false;
    }

    /**
     * Checks the current request for a valid access credential.
     *
     * The configured secret itself is used as the GET/POST parameter *name*
     * (e.g. ?thisIsMySecret123) - presence of that parameter is enough to unlock access.
     *
     * Only GET and POST are consulted (never the generic request bag), so that a cookie
     * of the same name cannot be used to unlock access.
     *
     * @param   string  $secretKey  Configured secret.
     *
     * @return boolean
     */
    private function requestContainsValidSecret(string $secretKey): bool
    {
        $input = $this->getApplication()->getInput();

        return $input->get->get($secretKey, null, 'RAW') !== null
            || $input->post->get($secretKey, null, 'RAW') !== null;
    }

    /**
     * Grants access for the current session.
     *
     * @param   string  $secretKey  Configured secret.
     *
     * @return void
     */
    private function grantSessionAccess(string $secretKey): void
    {
        $session = $this->getApplication()->getSession();

        $session->set(self::SESSION_KEY, hash('sha256', $secretKey));
        $session->set(self::LEGACY_SESSION_KEY, false);
    }

    /**
     * Blocks the current com_users request and redirects the visitor.
     *
     * @param   string  $secretKey  Configured secret, used only to redact logs.
     *
     * @return void
     */
    private function blockRequest(string $secretKey): void
    {
        $app = $this->getApplication();

        if ((int) $this->params->get('enableLogging', 0) === 1) {
            $this->logAddress(true, $secretKey);
        }

        if ((int) $this->params->get('messageOutput', 0) === 1) {
            $app->enqueueMessage(Text::_('PLG_HRZ_DISABLELOGIN_MESSAGE_ERROR_ACCESS_DENIED'), 'error');
        }

        $app->redirect($this->getRedirectUrl(), 303);
    }

    /**
     * Blocks a request made against the Joomla Web Services API.
     *
     * @param   string  $secretKey  Configured secret, used only to redact logs.
     *
     * @return void
     */
    private function blockApiRequest(string $secretKey): void
    {
        $app = $this->getApplication();

        if ((int) $this->params->get('enableLogging', 0) === 1) {
            $this->logAddress(true, $secretKey);
        }

        $app->setHeader('status', 403, true);
        $app->sendHeaders();

        echo json_encode([
            'errors' => [
                [
                    'title' => Text::_('PLG_HRZ_DISABLELOGIN_MESSAGE_ERROR_ACCESS_DENIED'),
                    'code'  => 403,
                ],
            ],
        ]);

        $app->close();
    }

    /**
     * Builds and validates the configured redirect URL.
     *
     * Empty value: Joomla root URL.
     * Leading slash: path relative to Joomla root.
     * Absolute value: only http:// and https:// URLs are accepted.
     * Other relative values: path relative to Joomla root.
     *
     * @return string
     */
    private function getRedirectUrl(): string
    {
        $redirectUrl = trim((string) $this->params->get('redirectUrl', ''));
        $root        = rtrim(Uri::root(), '/') . '/';

        if ($redirectUrl === '') {
            return $root;
        }

        if (preg_match('#^https?://#i', $redirectUrl) === 1) {
            $parts = parse_url($redirectUrl);

            if (is_array($parts) && !empty($parts['host'])) {
                return $redirectUrl;
            }

            return $root;
        }

        return $root . ltrim($redirectUrl, '/');
    }

    /**
     * Writes a log entry for the current request without leaking the configured secret.
     *
     * @param   boolean  $blocked    Whether the request was blocked.
     * @param   string   $secretKey  Configured secret to redact from the query string.
     *
     * @return void
     */
    private function logAddress(bool $blocked, string $secretKey): void
    {
        if (!self::$loggerRegistered) {
            Log::addLogger(
                ['text_file' => 'plg_hrz_disablelogin.log.php'],
                Log::ALL,
                ['plg_hrz_disablelogin']
            );

            self::$loggerRegistered = true;
        }

        $uri = clone Uri::getInstance();
        $uri->delVar($secretKey);

        $message = $blocked
            ? Text::_('PLG_HRZ_DISABLELOGIN_LOG_MSG_BLOCKED')
            : Text::_('PLG_HRZ_DISABLELOGIN_LOG_MSG_NOT_BLOCKED');

        Log::add($message . $uri->toString(), Log::DEBUG, 'plg_hrz_disablelogin');
    }

    /**
     * Returns the client's IP address, honouring Joomla's trusted-proxy configuration.
     *
     * @return string
     */
    private function getClientIp(): string
    {
        if (class_exists(IpHelper::class)) {
            return (string) IpHelper::getIp();
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Checks whether the given IP address is covered by the configured allow list.
     *
     * @param   string  $ip  The IP address to check.
     *
     * @return boolean
     */
    private function isAllowedIp(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $list = trim((string) $this->params->get('allowedIPs', ''));

        if ($list === '') {
            return false;
        }

        foreach (preg_split('/[\r\n,]+/', $list, -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            if ($this->ipMatches($ip, trim($entry))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether an IP address matches a single allow-list entry (exact address or CIDR range).
     *
     * @param   string  $ip       The IP address to check.
     * @param   string  $pattern  A single IP address or CIDR range (e.g. 203.0.113.4 or 203.0.113.0/24).
     *
     * @return boolean
     */
    private function ipMatches(string $ip, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if (strpos($pattern, '/') === false) {
            return strcasecmp($ip, $pattern) === 0;
        }

        [$subnet, $prefix] = explode('/', $pattern, 2);

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $prefix = (int) $prefix;
        $bytes  = intdiv($prefix, 8);
        $bits   = $prefix % 8;

        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $bits)) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}

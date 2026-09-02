<?php

/**
 * @package     plg_system_disablelogin
 * @copyright   (c) 2021 Stefan Herzog
 * @license     GNU/GPL, http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\DisableLogin\Extension\DisableLogin;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the plugin in Joomla's dependency injection container.
     *
     * The dispatcher is passed as the constructor's first argument (the classic,
     * two-argument CMSPlugin calling style). Joomla 5.3 deprecated this style in favour of
     * passing only the config array, but the deprecated style is still fully supported
     * throughout Joomla 4.2 - 6.x (removal is only planned for 7.0), so using it here keeps
     * the plugin compatible with the entire 4.2-6.x range without version-specific branching.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = PluginHelper::getPlugin('system', 'disablelogin');
                $subject    = new DisableLogin($dispatcher, (array) $plugin);

                $subject->setApplication(Factory::getApplication());

                return $subject;
            }
        );
    }
};

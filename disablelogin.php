<?php defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class PlgSystemDisableLogin extends CMSPlugin
{
    public function onAfterInitialise() {
        $this->disableLogin();
    }

    protected function disableLogin() {
        $app = Factory::getApplication();
        if ($app->isClient('site') === false) return;

        $uri = Uri::getInstance();

        // Search for string in URI
        if(strpos($uri, 'component/user') !== false) { // I missed out the 's' at the end of 'users' to have a more restrictive condition
          $this->redirect();
        }

        // Check if com_users is used
        $option  = $app->input->getCmd('option');
        if ($option == 'com_users') {
            $this->redirect();
        }
    }

    protected function redirect() {
        $Itemid = $this->getHomePageItemid();
        $app = Factory::getApplication();
        $link = Route::_('index.php?Itemid=' . $Itemid);
        Factory::getApplication()->enqueueMessage('Zugang verweigert', 'error');
        $app->redirect($link);
    }

    protected function getHomePageItemid() {
        $tableName = '#__menu';
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select('id');
        $query->from($db->quoteName($tableName));
        $query->where($db->quoteName('published') . ' = ' . $db->quote(1));
        $query->where($db->quoteName('home') . ' = ' . $db->quote(1));
        $db->setQuery($query);
        $data = $db->loadResult();
        return $data;
    }
}

<?php
/**
 * @package 	plg_hrz_disablelogin
 * @copyright 	(c) 2021 Stefan Herzog
 * @license		GNU/GPL, http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class PlgSystemDisableLogin extends CMSPlugin
{

    private $uri = '';
    
    public function onAfterInitialise() {
        $this->disableLogin();
    }

    protected function disableLogin() {
        $app = Factory::getApplication();
        if ($app->isClient('site') === false) return;

        $this->uri = Uri::getInstance();

        // Search for string in URI
        if(strpos( $this->uri, 'component/user') !== false) { // I missed out the 's' at the end of 'users' to have a more restrictive condition
          $this->redirect();
          return;
        }

        // Check if com_users is used
        $option  = $app->input->getCmd('option');
        if ($option == 'com_users') {
            $this->redirect();
            return;
        }
        
        // @todo: Add logging for all processed URLs
        // @body: This allows finding not-working addresses which are supposed to work. Add URL-SEO translation if possible
        
        // Log address which is NOT blocked
        if( $this->params->get('enableLogging') )$this->logAddress(false);
    }

    protected function redirect() {
        // Log address which is blocked
        if( $this->params->get('enableLogging') )$this->logAddress(true);
        
        $Itemid = $this->getHomePageItemid();
        $app = Factory::getApplication();
        $link = Route::_('index.php?Itemid=' . $Itemid);
        
        Factory::getApplication()->enqueueMessage(PLG_HRZ_DISABLELOGIN_MESSAGE_ACCESS_DENIED, 'error');
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
    
    private function logAddress($blocked) {
        	JLog::addLogger(
        	    array(
        	         // Sets file name
        	         'text_file' => 'plg_hrz_disablelogin.log.php'
        	    ),
        	    // Sets messages of all log levels to be sent to the file.
        	    JLog::ALL,
        	    // The log category/categories which should be recorded in this file.
        	    // We still need to put it inside an array.
        	    array('plg_hrz_disablelogin')
        	);
        	
        	$msg = ($blocked) ? PLG_HRZ_DISABLELOGIN_LOG_MSG_BLOCKED : PLG_HRZ_DISABLELOGIN_LOG_MSG_NOT_BLOCKED;
        	JLog::add(JText::_($msg . $this->url), JLog::DEBUG, 'plg_hrz_disablelogin');
    }
}

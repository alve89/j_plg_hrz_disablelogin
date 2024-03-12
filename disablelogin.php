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
    /**
    * Load the language file on instantiation.
    *
    * @var    boolean
    * @since  3.1
    */
    protected $autoloadLanguage = true;
    private $currentUri = '';
  	private $redirectUri = '';
    private $correctKey = false;
    private $app = NULL;
    private $session = NULL;

    public function onAfterInitialise() {
      $this->app = Factory::getApplication();
      $this->session= JFactory::getSession();

      // Use this plugin only in frontend
      if($this->app->isClient('site') === false) return;

      // Check if secret key is provided or if it already was provided in the current session
      if(is_null($this->params->get('secretKey'))) {
        $this->app->enqueueMessage(JText::_('PLG_HRZ_DISABLELOGIN_MESSAGE_WARNING_NO_SECRET'), 'WARNING');
        return;
      }
      elseif($this->session->get('enablelogin')) {
        return;
      }

      // Check if security key has been entered
      $this->correctKey = !is_null($this->app->input->get($this->params->get('secretKey')));

      // Check if correct key was provided with URL
      if($this->correctKey) {
        // Set session variable to "store" status "access granted"
        $this->session->set('enablelogin', true);
        return;
      }
      else {
        // Set correct URIs to perform redirect
        $this->setUris();
        // If user is not allowed, disable login
        $this->disableLogin();
      }
    }

    protected function disableLogin() {
        // Check if com_users is used in URI
        if(strpos( $this->currentUri, 'component/user') !== false) { // I missed out the 's' at the end of 'users' to have a more restrictive condition
          $this->redirect();
          return;
        }

        // Check if com_users is used
        $option  = $this->app->input->getCmd('option');
        if ($option == 'com_users') {
            $this->redirect();
            return;
        }

        // Log address which is NOT blocked
        if( $this->params->get('enableLogging') )$this->logAddress(false);
    }

    protected function redirect() {
        // Log address which is blocked
        if( $this->params->get('enableLogging') ) $this->logAddress(true);

        // If error message is set as to be displayed, show it
        if($this->params->get('messageOutput')) $this->app->enqueueMessage(JText::_('PLG_HRZ_DISABLELOGIN_MESSAGE_ERROR_ACCESS_DENIED'), 'error');

        // Perform the redirect
        $this->app->redirect($this->redirectUri->toString());
    }

    private function setUris() {

      // Get current URI to check it later
      $this->currentUri = Uri::getInstance();

  		// Set redirect URI: Use specified one (from plugin configuration) or default (Joomla Home)
  		// redirectUri is either http(s)://mydomain.tld or, if Joomla is installed in subdirectory, http(s)://mydomain.tld/path/to/joomla
  		// It's not depending on where it is called from (site/admin)
  	 if(!is_null($this->params->get('redirectUrl')) && substr($this->params->get('redirectUrl'),0,4) == 'http') {
  		 // If a valid URI was set, use this
  		 $this->redirectUri = Uri::getInstance($this->params->get('redirectUrl'));
  	 }
  	 // If an relative path was set, use this)
  	 else if( !is_null($this->params->get('redirectUrl')) && substr($this->params->get('redirectUrl'),0,1) == '/') {
  		 // Set redirect URI (remove leading slash first)
  		 $this->redirectUri = Uri::getInstance(Uri::root().substr($this->params->get('redirectUrl'),1));
  	 }
  	 else {
  		 // Otherwise use the default URI (Joomlas home page)
       $Itemid = $this->getHomePageItemid();
       $link = Route::_('index.php?Itemid=' . $Itemid);
       $this->redirectUri = Uri::getInstance($link);
  	 }
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

        	$msg = ($blocked) ? JText::_('PLG_HRZ_DISABLELOGIN_LOG_MSG_BLOCKED') : JText::_('PLG_HRZ_DISABLELOGIN_LOG_MSG_NOT_BLOCKED');
        	JLog::add($msg . $this->currentUri, JLog::DEBUG, 'plg_hrz_disablelogin');
    }
}

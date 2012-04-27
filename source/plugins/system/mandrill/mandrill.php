<?php
/**
 * @author Daniel Dimitrov
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

class plgSystemMandrill extends JPlugin
{

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
	}


	public function onAfterInitialise()
	{

		$key = $this->params->get('apiKey');

		if (strlen($key)) {

			$path = JPATH_ROOT . '/plugins/system/mandrill/mailer/mail.php';

			JLoader::register('JMail', $path);
			JLoader::load('JMail');

		} else {
			return JError::raiseWarning(500, JText::_('PLG_SYSTEM_MANDRILL_NO_API_KEY_SPECIFIED'));
		}
	}

}


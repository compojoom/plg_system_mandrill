<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Daniel Dimitrov
 * Date: 26.04.12
 * Time: 19:55
 * To change this template use File | Settings | File Templates.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

//$array['key'] = 'f7ae1273-4593-4fa0-b66c-b5ce1a01b8cb';
//$array['message'] = array(
//	'html' => 'text',
//	"from_email" => "services@compojoom.com",
//	"to" => array(
//		array(
//			"email"=> "daniel@compojoom.com",
//			"name"=> "daniel"
//		)
//	)
//);

//var_dump(json_encode($array));
//die();
//$json= '{"key": "f7ae1273-4593-4fa0-b66c-b5ce1a01b8cb",
//"message":{"html": "example html", "text": "example text",
//"subject": "example subject", "from_email": "services@compojoom.com",
//"from_name": "example from_name", "to":[{"email": "daniel@compojoom.com",
//"name": "daniel"}],"track_opens":true,"track_clicks":true,
//"auto_text":true,"url_strip_qs":true,
//"tags":["example tags[]"],
//}}' ;
//$url = 'http://mandrillapp.com/api/1.0/users/info.json';
//$ch = curl_init();
//curl_setopt($ch, CURLOPT_URL, $url);
//curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//
//curl_setopt($ch, CURLOPT_POST, 1);
//curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($array));
//
////if ($this->params->get('secure')) {
////	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
////	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
////	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
////}
//
//$result = curl_exec($ch);
//
//var_dump($result);
////
//die();





/**
 * Joomla! Sendmail Plugin
 *
 * @package         Joomla
 * @subpackage    System
 */

class plgSystemMandrill extends JPlugin
{

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}


	public function onAfterInitialise()
	{

		$key = $this->params->get('apiKey');

		if (strlen($key)) {

			$path = JPATH_ROOT . '/plugins/system/mandrill/mailer/mail.php';

			JLoader::register('JMail', $path);
			JLoader::load('JMail');

		} else {
			return JError::raiseWarning(500, JText::_('PLG_SYSTEM_MANDRILL_NO_APIKEY_SPECIFIED'));
		}
	}

}

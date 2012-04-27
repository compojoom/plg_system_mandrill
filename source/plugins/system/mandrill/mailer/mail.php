<?php

/**
 * @author Daniel Dimitrov - http://compojoom.com
 *
 * This file is part of Freakedout Mailchimp STS integration.
 * It is a modified version of the standard Joomla JMail class
 *
 * Fmsts is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Fmsts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fmsts.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * @version        $Id: mail.php 14401 2010-01-26 14:10:00Z louis $
 * @package        Joomla.Framework
 * @subpackage    Mail
 * @copyright    Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license        GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('phpmailer.phpmailer');
jimport('joomla.mail.helper');

/**
 * E-Mail Class.  Provides a common interface to send e-mail from the Joomla! Framework
 *
 * @package     Joomla.Framework
 * @subpackage        Mail
 * @since        1.5
 */
class JMail extends PHPMailer
{

	private $apiKey = null;
	public $to = array();
	public $cc = array();
	public $bcc = array();
	public $attachment = array();
	protected static $instances = array();

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{

		$plugin = JPluginHelper::getPlugin('system', 'mandrill');
		$this->params = new JParameter($plugin->params);

		$this->apiKey = $this->params->get('apiKey');

		// phpmailer has an issue using the relative path for it's language files
		$this->SetLanguage('joomla', JPATH_PLATFORM . '/phpmailer/language/');

		jimport('joomla.error.log');
		// Get the date.
		$date = JFactory::getDate()->format('Y_m');

		// Add the logger.
		JLog::addLogger(
			array(
				'text_file' => 'plg_system_mandrill.log.' . $date . '.php'
			)

		);


	}

	/**
	 * Returns the global email object, only creating it
	 * if it doesn't already exist.
	 *
	 * NOTE: If you need an instance to use that does not have the global configuration
	 * values, use an id string that is not 'Joomla'.
	 *
	 * @param   string  $id  The id string for the JMail instance [optional]
	 *
	 * @return  JMail  The global JMail object
	 *
	 * @since   11.1
	 */
	public static function getInstance($id = 'Joomla')
	{
		if (empty(self::$instances[$id])) {
			self::$instances[$id] = new JMail;
		}

		return self::$instances[$id];
	}

	/**
	 * @return mixed True if successful, a JError object otherwise
	 */
	public function Send()
	{

		if (!$this->isDailyQuotaExeeded() && !count($this->cc)) {
			return $this->mandrillSend();
		} else {
			return $this->phpMailerSend();
		}
	}

	private function isDailyQuotaExeeded()
	{
		$url = $this->getMandrillUrl() . '/users/info.json';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('key' => $this->apiKey)));

		if ($this->params->get('secure')) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
		}

		$result = curl_exec($ch);
		curl_close($ch);
		$data = json_decode($result);

		$dailyQuota = $data->hourly_quota * 24;

		$sentToday = $data->stats->today->sent;


		if ((int)$dailyQuota <= (int)$sentToday) {

			$this->writeToLog('Daily message quota exceeded. Quota: ' . (int)$dailyQuota . ' Sent: ' . (int)$sentToday);

			return true;
		}
		return false;
	}

	private function mandrillSend()
	{

		$mandrill = new stdClass();
		$mandrill->key = $this->apiKey;
		$mandrill->message = array(
			'subject' => $this->Subject,
			'from_email' => $this->From,
			'from_name' => $this->FromName
		);

		// let us set some tags
		$input = JFactory::getApplication()->input;
		if ($input->get('option')) {
			$mandrill->message['tags'][] = $input->get('option');
		}
		if ($input->get('view')) {
			$mandrill->message['tags'][] = $input->get('view');
		}
		if ($input->get('task')) {
			$mandrill->message['tags'][] = $input->get('option');
		}


		if (count($this->ReplyTo) > 0) {
			$replyTo = array_keys($this->ReplyTo);
			$mandrill->message['headers'] = array('Reply-To' => $replyTo[0]);
		}

		if ($this->ContentType == 'text/plain') {
			$mandrill->message['text'] = $this->Body;
		} else {
			$mandrill->message['html'] = $this->Body;
			$message['auto_text'] = true;
		}

		$mandrill->message['track_opens'] = true;
		$mandrill->message['track_clicks'] = true;

		foreach ($this->to as $value) {
			$to[] = array(
				'email' => $value[0],
				'name' => $value[1]
			);
		}

		$mandrill->message['to'] = $to;

		$url = $this->getMandrillUrl() . '/messages/send.json';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mandrill));

		if ($this->params->get('secure')) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
		}

		$data = json_decode(curl_exec($ch));

		curl_close($ch);

		// check if we have have a correct response
		if (is_array($data)) {
			$rejected = array();
			foreach ($data as $value) {
				$status[$value->status][] = array($value->email, '');
			}

			if (count($status['queue'])) {
				$this->writeToLog('Emailing to ' . imploded(',', $status['queue']) . 'was queued by Mandrill. Trying to send the mail using phpMailer');
			}

			// if we have rejected emails - try to send them with phpMailer
			// not a perfect solution because we will return the result form phpMailer instead of the Mandrill
			// but better to try to deliver agian than to fail to send the message
			if (count($status['rejected'])) {
				$this->writeToLog('Emailing to ' . imploded(',', $status['rejected']) . 'was rejected by Mandrill. Trying to send the mail using phpMailer');
				$this->ClearAddresses();
				$this->addRecipient($rejected);
				return $this->phpMailerSend();
			}

			// let us hope that we always come so far!
			if (count($status['sent'])) {
				return true;
			}
		}


		return false;
	}

	private function phpMailerSend()
	{
		if (($this->Mailer == 'mail') && !function_exists('mail')) {
			return JError::raiseNotice(500, JText::_('JLIB_MAIL_FUNCTION_DISABLED'));
		}

		@ $result = parent::Send();

		if ($result == false) {
			// TODO: Set an appropriate error number
			$result = & JError::raiseNotice(500, JText::_($this->ErrorInfo));
		}
		return $result;
	}

	/**
	 * Set the email sender
	 *
	 * @param   array  $from  email address and Name of sender
	 *                        <code>array([0] => email Address [1] => Name)</code>
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function setSender($from)
	{
		if (is_array($from)) {
			// If $from is an array we assume it has an address and a name
			if (isset($from[2])) {
				// If it is an array with entries, use them
				$this->SetFrom(JMailHelper::cleanLine($from[0]), JMailHelper::cleanLine($from[1]), (bool)$from[2]);
			}
			else {
				$this->SetFrom(JMailHelper::cleanLine($from[0]), JMailHelper::cleanLine($from[1]));
			}
		}
		elseif (is_string($from)) {
			// If it is a string we assume it is just the address
			$this->SetFrom(JMailHelper::cleanLine($from));
		}
		else {
			// If it is neither, we throw a warning
			JError::raiseWarning(0, JText::sprintf('JLIB_MAIL_INVALID_EMAIL_SENDER', $from));
		}

		return $this;
	}

	/**
	 * Set the email subject
	 *
	 * @param   string  $subject  Subject of the email
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function setSubject($subject)
	{
		$this->Subject = JMailHelper::cleanLine($subject);

		return $this;
	}

	/**
	 * Set the email body
	 *
	 * @param   string  $content  Body of the email
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function setBody($content)
	{
		/*
		 * Filter the Body
		 * TODO: Check for XSS
		 */
		$this->Body = JMailHelper::cleanText($content);

		return $this;
	}

	/**
	 * Add recipients to the email
	 *
	 * @param   mixed  $recipient  Either a string or array of strings [email address(es)]
	 * @param   mixed  $name       Either a string or array of strings [name(s)]
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addRecipient($recipient, $name = '')
	{
		// If the recipient is an array, add each recipient... otherwise just add the one
		if (is_array($recipient)) {
			foreach ($recipient as $to) {
				$to = JMailHelper::cleanLine($to);
				$this->AddAddress($to);
			}
		}
		else {
			$recipient = JMailHelper::cleanLine($recipient);
			$this->AddAddress($recipient);
		}

		return $this;
	}

	/**
	 * This method is not implemented in Mailchimp's Mandrill, so we just log the attempt to send a CC
	 *
	 * @access public
	 * @param mixed $cc Either a string or array of strings [e-mail address(es)]
	 * @return void
	 * @since 1.5
	 */
	public function addCC($cc)
	{
		$message = 'the addCC method is not supported by the mailchip\'s Mandrill API. We will send this mail with PHPMailer';
		//If the carbon copy recipient is an aray, add each recipient... otherwise just add the one
		if (isset($cc)) {
			if (is_array($cc)) {
				foreach ($cc as $to) {
					$to = JMailHelper::cleanLine($to);
					parent::AddCC($to);

					$this->AddAnAddress('cc', $to, '');

					$this->writeToLog($message);
				}
			} else {
				$cc = JMailHelper::cleanLine($cc);
				parent::AddCC($cc);

				$this->AddAnAddress('cc', $cc, '');

				$this->writeToLog($message);
			}
		}


		return $this;

	}

	/**
	 * Add blind carbon copy recipients to the email
	 *
	 * @param   mixed  $bcc   Either a string or array of strings [email address(es)]
	 * @param   mixed  $name  Either a string or array of strings [name(s)]
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addBCC($bcc, $name = '')
	{
		// If the blind carbon copy recipient is an array, add each recipient... otherwise just add the one
		if (isset($bcc)) {
			if (is_array($bcc)) {
				foreach ($bcc as $to) {
					$to = JMailHelper::cleanLine($to);
					parent::AddBCC($to);
				}
			}
			else {
				$bcc = JMailHelper::cleanLine($bcc);
				parent::AddBCC($bcc);
			}
		}

		return $this;
	}

	/**
	 * Add file attachments to the email
	 *
	 * @param   mixed  $attachment  Either a string or array of strings [filenames]
	 * @param   mixed  $name        Either a string or array of strings [names]
	 * @param   mixed  $encoding    The encoding of the attachment
	 * @param   mixed  $type        The mime type
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addAttachment($attachment, $name = '', $encoding = 'base64', $type = 'application/octet-stream')
	{
		// If the file attachments is an array, add each file... otherwise just add the one
		if (isset($attachment)) {
			if (is_array($attachment)) {
				foreach ($attachment as $file) {
					parent::AddAttachment($file, $name, $encoding, $type);
				}
			}
			else {
				parent::AddAttachment($attachment, $name, $encoding, $type);
			}
		}

		return $this;
	}

	/**
	 * This function is a copy of the PHPMailer 5.1 function AddAnAddress
	 * We need to call it also in this class, because otherwise we don't have
	 * access to the private to, cc and bcc variables... We don't need to change
	 * the method name as phpmailer has declared AddAnAddress as private and it
	 * is in different scope.
	 *
	 * Adds an address to one of the recipient arrays
	 * Addresses that have been added already return false, but do not throw exceptions
	 * @param string $kind One of 'to', 'cc', 'bcc', 'ReplyTo'
	 * @param string $address The email address to send to
	 * @param string $name
	 * @return boolean true on success, false if address already used or invalid in some way
	 * @access private
	 */
//	private function AddAnAddress($kind, $address, $name = '')
//	{
//		if (!preg_match('/^(to|cc|bcc|ReplyTo)$/', $kind)) {
//			echo 'Invalid recipient array: ' . kind;
//			return false;
//		}
//		$address = trim($address);
//		$name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
//		if (!self::ValidateAddress($address)) {
//			$this->SetError($this->Lang('invalid_address') . ': ' . $address);
//			if ($this->exceptions) {
//				throw new phpmailerException($this->Lang('invalid_address') . ': ' . $address);
//			}
//			echo $this->Lang('invalid_address') . ': ' . $address;
//			return false;
//		}
//		if ($kind != 'ReplyTo') {
//			if (!isset($this->all_recipients[strtolower($address)])) {
//				array_push($this->$kind, array($address, $name));
//				$this->all_recipients[strtolower($address)] = true;
//				return true;
//			}
//		} else {
//			if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
//				$this->ReplyTo[strtolower($address)] = array($address, $name);
//				return true;
//			}
//		}
//		return false;
//	}

//	/**
//	 * This method is not implemented in Mailchimp's STS, so we just log the attempt to add an attachment
//	 *
//	 * @access public
//	 * @param mixed $attachment Either a string or array of strings [filenames]
//	 * @return void
//	 * @since 1.5
//	 */
//	public function addAttachment($attachment)
//	{
//		$message = 'The addAttachment method is not supported by Mailchimp\'s STS API. We will send this mail using PHPMailer';
//		// If the file attachments is an aray, add each file... otherwise just add the one
//		if (isset($attachment)) {
//			if (is_array($attachment)) {
//				foreach ($attachment as $file) {
//					parent::AddAttachment($file);
//					$this->AddAttachmentJMail($file);
//
//					$this->writeToLog($message);
//				}
//			} else {
//				parent::AddAttachment($file);
//				$this->AddAttachmentJMail($file);
//				$this->writeToLog($message);
//			}
//		}
//
//		return $this;
//
//	}

	/**
	 * Add Reply to email address(es) to the email
	 *
	 * @param   array  $replyto  Either an array or multi-array of form
	 *                           <code>array([0] => email Address [1] => Name)</code>
	 * @param array|string $name Either an array or single string
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addReplyTo($replyto, $name = '')
	{
		// Take care of reply email addresses
		if (is_array($replyto[0])) {
			foreach ($replyto as $to) {
				$to0 = JMailHelper::cleanLine($to[0]);
				$to1 = JMailHelper::cleanLine($to[1]);
				parent::AddReplyTo($to0, $to1);
			}
		}
		else {
			$replyto0 = JMailHelper::cleanLine($replyto[0]);
			$replyto1 = JMailHelper::cleanLine($replyto[1]);
			parent::AddReplyTo($replyto0, $replyto1);
		}

		return $this;
	}

	/**
	 * Use sendmail for sending the email
	 *
	 * @param   string  $sendmail  Path to sendmail [optional]
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function useSendmail($sendmail = null)
	{
		$this->Sendmail = $sendmail;

		if (!empty($this->Sendmail)) {
			$this->IsSendmail();

			return true;
		}
		else {
			$this->IsMail();

			return false;
		}
	}

	/**
	 * Use SMTP for sending the email
	 *
	 * @param   string   $auth    SMTP Authentication [optional]
	 * @param   string   $host    SMTP Host [optional]
	 * @param   string   $user    SMTP Username [optional]
	 * @param   string   $pass    SMTP Password [optional]
	 * @param   string   $secure  Use secure methods
	 * @param   integer  $port    The SMTP port
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function useSMTP($auth = null, $host = null, $user = null, $pass = null, $secure = null, $port = 25)
	{
		$this->SMTPAuth = $auth;
		$this->Host = $host;
		$this->Username = $user;
		$this->Password = $pass;
		$this->Port = $port;

		if ($secure == 'ssl' || $secure == 'tls') {
			$this->SMTPSecure = $secure;
		}

		if (($this->SMTPAuth !== null && $this->Host !== null && $this->Username !== null && $this->Password !== null)
			|| ($this->SMTPAuth === null && $this->Host !== null)
		) {
			$this->IsSMTP();

			return true;
		}
		else {
			$this->IsMail();

			return false;
		}
	}

//	/**
//	 *
//	 * @return string - the datacenter to use from the apiKey
//	 */
//	private function getDataCenter()
//	{
//		$dc = "us1";
//		if (strstr($this->apiKey, "-")) {
//			list($key, $dc) = explode("-", $this->apiKey, 2);
//			if (!$dc)
//				$dc = "us1";
//		}
//
//		return $dc;
//	}

	/**
	 * @return string - the url to mailchimp api
	 */
	private function getMandrillUrl()
	{

		$scheme = 'http';

		if ($this->params->get('secure')) {
			$scheme = 'https';
		}

		$url = $scheme . '://mandrillapp.com/api/1.0';

		return $url;
	}

	/**
	 *
	 * @param $message
	 */
	private function writeToLog($message)
	{
		JLog::add($message, JLog::WARNING);
	}

	/**
	 * Function to send an email
	 *
	 * @param   string   $from         From email address
	 * @param   string   $fromName     From name
	 * @param   mixed    $recipient    Recipient email address(es)
	 * @param   string   $subject      email subject
	 * @param   string   $body         Message body
	 * @param bool|int $mode false = plain text, true = HTML
	 * @param   mixed    $cc           CC email address(es)
	 * @param   mixed    $bcc          BCC email address(es)
	 * @param   mixed    $attachment   Attachment file name(s)
	 * @param   mixed    $replyTo      Reply to email address(es)
	 * @param   mixed    $replyToName  Reply to name(s)
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function sendMail($from, $fromName, $recipient, $subject, $body, $mode = 0, $cc = null, $bcc = null, $attachment = null, $replyTo = null,
	                         $replyToName = null)
	{
		$this->setSender(array($from, $fromName));
		$this->setSubject($subject);
		$this->setBody($body);

		// Are we sending the email as HTML?
		if ($mode) {
			$this->IsHTML(true);
		}

		$this->addRecipient($recipient);
		$this->addCC($cc);
		$this->addBCC($bcc);
		$this->addAttachment($attachment);

		// Take care of reply email addresses
		if (is_array($replyTo)) {
			$numReplyTo = count($replyTo);

			for ($i = 0; $i < $numReplyTo; $i++) {
				$this->addReplyTo(array($replyTo[$i], $replyToName[$i]));
			}
		}
		elseif (isset($replyTo)) {
			$this->addReplyTo(array($replyTo, $replyToName));
		}

		return $this->Send();
	}

	/**
	 * Sends mail to administrator for approval of a user submission
	 *
	 * @param   string  $adminName   Name of administrator
	 * @param   string  $adminEmail  Email address of administrator
	 * @param   string  $email       [NOT USED TODO: Deprecate?]
	 * @param   string  $type        Type of item to approve
	 * @param   string  $title       Title of item to approve
	 * @param   string  $author      Author of item to approve
	 * @param   string  $url         A URL to included in the mail
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function sendAdminMail($adminName, $adminEmail, $email, $type, $title, $author, $url = null)
	{
		$subject = JText::sprintf('JLIB_MAIL_USER_SUBMITTED', $type);

		$message = sprintf(JText::_('JLIB_MAIL_MSG_ADMIN'), $adminName, $type, $title, $author, $url, $url, 'administrator', $type);
		$message .= JText::_('JLIB_MAIL_MSG') . "\n";

		$this->addRecipient($adminEmail);
		$this->setSubject($subject);
		$this->setBody($message);

		return $this->Send();
	}

}

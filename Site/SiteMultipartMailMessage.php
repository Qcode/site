<?php

require_once 'Swat/SwatDate.php';
require_once 'Site/SiteObject.php';
require_once 'Site/exceptions/SiteMailException.php';

require_once 'Mail.php';
require_once 'Mail/mime.php';

/**
 * Multipart text/html email message
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteMultipartMailMessage extends SiteObject
{
	// {{{ public properties

	/**
	 * Email subject
	 *
	 * @var string
	 */
	public $subject = '';

	/**
	 * Recipient's email address
	 *
	 * @var string
	 */
	public $to_address = null;

	/**
	 * Recipient's name
	 *
	 * @var string
	 */
	public $to_name = '';

	/**
	 * Sender's email address
	 *
	 * @var string
	 */
	public $from_address = null;

	/**
	 * Sender's name
	 *
	 * @var string
	 */
	public $from_name = '';

	/**
	 * Addresses to which to carbon-copy (CC) this mail message
	 *
	 * @var array
	 */
	public $cc_list = array();

	/**
	 * Addresses to which to blind-carbon-copy (BCC) this mail message
	 *
	 * @var array
	 */
	public $bcc_list = array();

	/**
	 * Sender's reply-to address
	 *
	 * @var string
	 */
	public $reply_to_address = null;

	/**
	 * Return path for bounces
	 *
	 * The return path should be set to an address owned by the site or
	 * service sending mail to appear authentic in SPF checks.
	 *
	 * @var string
	 */
	public $return_path = null;

	/**
	 * Sender of this email
	 *
	 * Can be use for user-initiated emails sent by a site or service. The
	 * 'sender' can be the site or service and the 'from' can be the user.
	 * This allows for sending emails on behalf of a user and passing SPF
	 * checks.
	 *
	 * @var string
	 */
	public $sender = null;

	/**
	 * Sender's name.
	 *
	 * Name for the sender when using the sender header for user-initiated
	 * emails sent by a site or service.
	 *
	 * @var string
	 */
	public $sender_name = null;

	/**
	 * Text body
	 *
	 * @var string
	 */
	public $text_body = '';

	/**
	 * HTML body
	 *
	 * @var string
	 */
	public $html_body = '';

	/**
	 * SMTP server address
	 *
	 * @var string
	 */
	public $smtp_server = null;

	/**
	 * SMTP port
	 *
	 * @var integer
	 */
	public $smtp_port = null;

	/**
	 * SMTP username
	 *
	 * @var string
	 */
	public $smtp_username = null;

	/**
	 * SMTP password
	 *
	 * @var string
	 */
	public $smtp_password = null;

	/**
	 * Files to attach to this mail message
	 *
	 * @var array
	 */
	public $attachments = array();

	// }}}
	// {{{ protected properties

	/**
	 * The application sending mail
	 *
	 * @var SiteApplication
	 */
	protected $app = null;

	/**
	 * Date of the email.
	 *
	 * @var SwatDate
	 */
	protected $date;

	/**
	 * Data to include with this mail message as attachments
	 *
	 * @var array
	 *
	 * @see SiteMultipartMailMessage::addAttachmentFromString()
	 */
	protected $string_attachments = array();

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new multipart mail message
	 *
	 * @param SiteApplication $app the application sending this mail message.
	 */
	public function __construct(SiteApplication $app)
	{
		$this->app = $app;

		$this->date = new SwatDate();
		$this->date->toUTC();
	}

	// }}}
	// {{{ public function send()

	/**
	 * Sends a multi-part email
	 */
	public function send()
	{
		// create multipart-mime message
		$crlf = "\n";
		$mime = new Mail_mime($crlf);

		$mime->setSubject($this->subject);
		$mime->setFrom(
			$this->getAddressHeader(
				$this->from_address,
				$this->from_name
			)
		);

		$mime->setTXTBody($this->text_body);
		$mime->setHTMLBody($this->convertCssToInlineStyles($this->html_body));

		foreach ($this->cc_list as $address)
			$mime->addCc($address);

		foreach ($this->bcc_list as $address)
			$mime->addBcc($address);

		// file attachments
		foreach ($this->attachments as $attachment) {
			$mime->addAttachment($attachment);
		}

		// attachments with metadata
		foreach ($this->string_attachments as $attachment) {
			$mime->addAttachment(
				$attachment['data'],
				$attachment['content_type'],
				$attachment['filename'],
				false
			);
		}

		// create mailer
		$email_params = array();
		$email_params['host'] = $this->smtp_server;

		if ($this->smtp_port != '') {
			$email_params['port'] = $this->smtp_port;
		}

		if ($this->smtp_username != '') {
			$email_params['username'] = $this->smtp_username;
		}

		if ($this->smtp_password != '') {
			$email_params['auth'] = true;
			$email_params['password'] = $this->smtp_password;
		}

		$mailer = Mail::factory('smtp', $email_params);

		if (PEAR::isError($mailer)) {
			throw new SiteMailException($mailer);
		}

		// create additional mail headers
		$headers = array();

		if ($this->return_path != '') {
			$headers['Return-Path'] = $this->return_path;
		}

		$headers['Date'] = $this->date->getRFC2822();

		$headers['To'] = $this->getAddressHeader(
			$this->to_address,
			$this->to_name
		);

		if ($this->reply_to_address != '') {
			$headers['Reply-To'] = $this->reply_to_address;
		}

		if ($this->sender != '') {
			$headers['Sender'] =  $this->getAddressHeader(
				$this->sender,
				$this->sender_name
			);
		}

		// create email body and headers
		$mime_params = array();
		$mime_params['head_charset'] = 'UTF-8';
		$mime_params['text_charset'] = 'UTF-8';
		$mime_params['text_encoding'] = 'quoted-printable';
		$mime_params['html_charset'] = 'UTF-8';
		$mime_params['html_encoding'] = 'quoted-printable';
		$body = $mime->get($mime_params);
		$headers = $mime->headers($headers);

		// send email
		$result = $mailer->send($this->getRecipients(), $headers, $body);

		if ($this->app->config->email->log) {
			$this->logMessage();
		}

		if (PEAR::isError($result))
			throw new SiteMailException($result);
	}

	// }}}
	// {{{ public function addCc()

	/**
	 * Adds an email address to the bcc list
	 *
	 * @param string $email the email address to add.
	 */
	public function addCc($email)
	{
		$this->cc_list[] = $email;
	}

	// }}}
	// {{{ public function addBcc()

	/**
	 * Adds an email address to the bcc list
	 *
	 * @param string $email the email address to add.
	 */
	public function addBcc($email)
	{
		$this->bcc_list[] = $email;
	}

	// }}}
	// {{{ public function addAttachmentFromString()

	public function addAttachmentFromString($data, $filename = null,
		$content_type = null)
	{
		$this->string_attachments[] = array(
			'data'         => $data,
			'filename'     => $filename,
			'content_type' => $content_type,
		);
	}

	// }}}
	// {{{ protected function getAddressHeader()

	protected function getAddressHeader($address, $name = '')
	{
		$header = ($name != '') ?
			'"%2$s" <%1$s>' :
			'%1$s';

		return sprintf($header, $address, $name);
	}

	// }}}
	// {{{ protected function getRecipients()

	protected function getRecipients()
	{
		$recipients = array($this->to_address);

		// add cc addresses
		$recipients = array_merge($recipients, $this->cc_list);

		// add bcc addresses
		$recipients = array_merge($recipients, $this->bcc_list);

		return implode(', ', $recipients);
	}

	// }}}
	// {{{ protected function logMessage()

	protected function logMessage()
	{
		// Log details that would be useful for statistics.
		$sql = 'insert into SiteEmailLog
			(createdate, instance, type, attachment_count, attachment_size,
			to_address, from_address, recipient_type) values %s';

		$values_sql = '(%s, %s, %s, %s, %s, %%s, %s, %%s)';

		$attachment_size = 0;

		// file attachment support
		foreach ($this->attachments as $attachment) {
			$attachment_size += filesize($attachment);
		}

		// string attachments with metadata
		foreach ($this->string_attachments as $attachment) {
			$attachment_size += mb_strlen($attachment['data'], '8bit');
		}

		$attachment_count = count($this->attachments) +
			count($this->string_attachments);

		$values_sql = sprintf($values_sql,
			$this->app->db->quote($this->date, 'date'),
			$this->app->db->quote($this->app->getInstanceId(), 'integer'),
			$this->app->db->quote(get_class($this), 'text'),
			$this->app->db->quote($attachment_count, 'integer'),
			$this->app->db->quote($attachment_size, 'integer'),
			$this->app->db->quote($this->from_address), 'text');

		$values = array();
		$values[] = sprintf($values_sql,
			$this->app->db->quote($this->to_address, 'text'),
			$this->app->db->quote('to', 'text'));

		foreach ($this->cc_list as $recipient) {
			$values[] = sprintf($values_sql,
				$this->app->db->quote($recipient, 'text'),
				$this->app->db->quote('cc', 'text'));
		}

		foreach ($this->bcc_list as $recipient) {
			$values[] = sprintf($values_sql,
				$this->app->db->quote($recipient, 'text'),
				$this->app->db->quote('bcc', 'text'));
		}

		$sql = sprintf($sql, implode(',', $values));

		SwatDB::exec($this->app->db, $sql);
	}

	// }}}
	// {{{ protected function convertCssToInlineStyles()

	/**
	 * Attempt to convert css to inline styles
	 */
	protected function convertCssToInlineStyles($html)
	{
		// Emogrifier is optional. If not included, just return the regular
		// HTML with inline CSS
		if ($html != '' && class_exists('Emogrifier')) {
			$reset_errors = libxml_use_internal_errors(true);
			$emogrifier = new Emogrifier($html);
			$inlined = $emogrifier->emogrify();

			// log errors so we can find XML defects
			$errors = libxml_get_errors();
			libxml_clear_errors();
			libxml_use_internal_errors($reset_errors);
			if (count($errors) > 0) {
				$this->logLibXmlErrors($html, $errors);
			}
		} else {
			$inlined = $html;
		}

		return $inlined;
	}

	// }}}
	// {{{ protected function logLibXmlErrors()

	/**
	 * Attempt to convert css to inline styles
	 */
	protected function logLibXmlErrors($html, array $errors)
	{
		$error_message = '';
		foreach ($errors as $error) {
			$error_message.= sprintf(
				"Message: %s\n".
				"Code: %s\n".
				"Line: %s, Column: %s",
				$error->message,
				$error->code,
				$error->line,
				$error->column);
		}

		$error_message.= "\n\nInput XML\n\n:".$html;

		$exception = new SwatException($error_message);
		$exception->processAndContinue();
	}

	// }}}
}

?>

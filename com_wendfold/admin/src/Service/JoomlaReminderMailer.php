<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;

/**
 * Reminder mailer using Joomla 6 MailerFactoryInterface.
 *
 * Failures expose raw detail via lastError() only. Callers log with profile
 * and event ids after running the text through MailerErrorRedactor.
 *
 * @since  1.0.0
 */
final class JoomlaReminderMailer implements ReminderMailer
{
	/**
	 * Raw detail from the most recent failed send().
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $lastError = '';

	/**
	 * @param   string  $fromEmail  Global mailfrom.
	 * @param   string  $fromName   From display name.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private string $fromEmail, private string $fromName)
	{
	}

	/**
	 * Send one plain-text reminder. True only when the mailer reports success.
	 *
	 * @param   string  $email    Recipient address.
	 * @param   string  $name     Recipient name.
	 * @param   string  $subject  Subject line.
	 * @param   string  $body     Plain-text body.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function send(string $email, string $name, string $subject, string $body): bool
	{
		$this->lastError = '';

		if ($email === '')
		{
			return false;
		}

		try
		{
			$mailer = clone Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

			if ($this->fromEmail !== '')
			{
				$mailer->setSender([$this->fromEmail, $this->fromName]);
			}

			$mailer->addRecipient($email, $name);
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->isHtml(false);

			if ($mailer->Send() === true)
			{
				return true;
			}

			$this->lastError = self::mailerErrorInfo($mailer);

			return false;
		}
		catch (\Throwable $e)
		{
			$this->lastError = $e->getMessage();

			return false;
		}
	}

	/**
	 * Raw mailer or exception detail from the most recent failed send().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function lastError(): string
	{
		return $this->lastError;
	}

	/**
	 * Read PHPMailer's ErrorInfo without a camelCaps property name in source.
	 *
	 * @param   object  $mailer  Joomla mailer / PHPMailer instance.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function mailerErrorInfo(object $mailer): string
	{
		$vars = get_object_vars($mailer);

		return trim((string) ($vars['ErrorInfo'] ?? ''));
	}
}

<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

/**
 * Shared gate for bulk / blast email.
 *
 * Super Users pass authorise() via core.admin. Administrators are allowed by the
 * component asset rule written on install and update. Every other group is denied
 * until an administrator allows wendfold.emailblast.
 *
 * @since  1.0.0
 */
final class EmailblastHelper
{
	/**
	 * Permission action for bulk and blast email.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const ACTION = 'wendfold.emailblast';

	/**
	 * Whether the user may send bulk email.
	 *
	 * @param   object|null  $user  User to test. Null uses the current identity.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function canSend($user = null): bool
	{
		$user = $user ?? Factory::getApplication()->getIdentity();

		return $user && $user->authorise(self::ACTION, 'com_wendfold');
	}

	/**
	 * Send one prepared blast message. Logs a redacted failure with the profile id.
	 *
	 * @param   object  $mail       Mailer already addressed and filled.
	 * @param   object  $recipient  Recipient row (expects id = profile id when present).
	 *
	 * @return  boolean  True when the mailer reports success.
	 *
	 * @since   1.0.0
	 */
	public static function attemptSend(object $mail, object $recipient): bool
	{
		$vars      = get_object_vars($recipient);
		$profileId = (int) ($vars['id'] ?? 0);

		try
		{
			if ($mail->Send())
			{
				return true;
			}

			self::logFailure($profileId, self::errorInfo($mail));

			return false;
		}
		catch (\Throwable $error)
		{
			self::logFailure($profileId, $error->getMessage());

			return false;
		}
	}

	/**
	 * Log a blast send failure with addresses redacted.
	 *
	 * @param   integer  $profileId  Recipient profile id.
	 * @param   string   $rawDetail  Mailer ErrorInfo or exception message.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function logFailure(int $profileId, string $rawDetail): void
	{
		if (!\class_exists(Log::class))
		{
			return;
		}

		Log::add(
			MailerErrorRedactor::append(
				'Email blast send failed for profile ' . $profileId,
				$rawDetail
			),
			Log::ERROR,
			'com_wendfold'
		);
	}

	/**
	 * Read PHPMailer's ErrorInfo via get_object_vars (name is not camel caps).
	 *
	 * @param   object  $mail  Joomla mailer / PHPMailer instance.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function errorInfo(object $mail): string
	{
		$vars = get_object_vars($mail);

		return (string) ($vars['ErrorInfo'] ?? '');
	}
}

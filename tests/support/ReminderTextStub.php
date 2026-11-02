<?php

/**
 * Wendfold - Text stub for reminder mail language keys.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\Language;

if (!\class_exists(Text::class, false))
{
	/**
	 * Enough of Text for ReminderService mail subjects and bodies.
	 *
	 * @since  1.0.0
	 */
	class Text
	{
		// phpcs:disable Joomla.NamingConventions.ValidFunctionName.MethodUnderscore -- mirrors Joomla Text

		/**
		 * @var    array<string, string>
		 * @since  1.0.0
		 */
		private const STRINGS = [
			'PLG_TASK_WENDFOLD_EMAIL_RENEWAL_SUBJECT' => 'Chapter Dues Renewal Reminder - %s Days',
			'PLG_TASK_WENDFOLD_EMAIL_RENEWAL_BODY'    => "Dear %s,\n\nYour chapter membership renews on %s (%s days).\n"
				. "Please contact your chapter secretary to renew.\n\nSee you there!",
			'PLG_TASK_WENDFOLD_EMAIL_EVENT_SUBJECT'   => 'Event Reminder: %s - Today!',
			'PLG_TASK_WENDFOLD_EMAIL_EVENT_BODY'      => "Hey %s!\n\nJust a reminder that today is %s.%s\n\nSee you there!",
			'COM_WENDFOLD_EMAIL_EVENT_REMINDER_LABEL' => 'Reminder:',
			'COM_WENDFOLD_EMAIL_EVENT_CANCEL_SUBJECT' => 'Event cancelled: %s',
			'COM_WENDFOLD_EMAIL_EVENT_CANCEL_BODY'    => "Hello %s,\n\nThe event \"%s\" on %s has been cancelled.\n"
				. "\n%s\n\nThis notice was sent by %s.",
		];

		/**
		 * @param   string  $string  Language key.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public static function _($string)
		{
			return self::STRINGS[$string] ?? (string) $string;
		}

		/**
		 * @param   string  $string  Language key.
		 * @param   mixed   ...$args Placeholder values.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public static function sprintf($string, ...$args)
		{
			return \sprintf(self::_($string), ...$args);
		}
	}
	// phpcs:enable Joomla.NamingConventions.ValidFunctionName.MethodUnderscore
}

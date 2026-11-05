<?php

/**
 * @package     Wendfold.Plugin
 * @subpackage  User.Wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Plugin\User\Wendfold\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Admin-notify mail subject and body for the user plugin.
 *
 * Loads plugin language explicitly so CLI and non-web user creates do not
 * leave raw PLG_ keys in the message.
 *
 * @since  1.0.0
 */
final class AdminNotifyMail
{
	/**
	 * Load plg_user_wendfold language strings for admin-notify mail.
	 *
	 * Tries the administrator language first, then the site default language
	 * tag, then the plugin language folder. Safe under CLI where the plugin
	 * language is not auto-loaded.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function loadLanguage(): void
	{
		try
		{
			$app         = Factory::getApplication();
			$lang        = $app->getLanguage();
			$siteDefault = (string) $app->get('language', 'en-GB');

			$lang->load('plg_user_wendfold', JPATH_ADMINISTRATOR)
				|| $lang->load('plg_user_wendfold', JPATH_ADMINISTRATOR, $siteDefault)
				|| $lang->load('plg_user_wendfold', JPATH_PLUGINS . '/user/wendfold')
				|| $lang->load('plg_user_wendfold', JPATH_PLUGINS . '/user/wendfold', $siteDefault);
		}
		catch (\Throwable $e)
		{
			// Text keys fall back to their literal names when language is unavailable.
		}
	}

	/**
	 * Build the admin-notify subject and body after loading plugin language.
	 *
	 * @param   string  $siteName   Site name for the subject.
	 * @param   int     $profileId  New profile id.
	 * @param   string  $name       Joomla user display name.
	 * @param   string  $username   Joomla username.
	 * @param   int     $userId     Joomla user id.
	 *
	 * @return  array{0: string, 1: string}  Subject and body.
	 *
	 * @since   1.0.0
	 */
	public static function build(
		string $siteName,
		int $profileId,
		string $name,
		string $username,
		int $userId
	): array
	{
		self::loadLanguage();

		$subject = Text::sprintf('PLG_USER_WENDFOLD_MAIL_SUBJECT', $siteName);
		$body    = Text::sprintf(
			'PLG_USER_WENDFOLD_MAIL_BODY',
			$profileId,
			$name,
			$username,
			$userId
		);

		return [$subject, $body];
	}
}

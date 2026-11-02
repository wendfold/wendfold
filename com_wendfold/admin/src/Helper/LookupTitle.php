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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;

/**
 * Display a lookup title that may be a language key or plain text.
 *
 * Seeds store keys such as COM_WENDFOLD_MEMBERSHIP_FULL. A chapter that
 * renames the row stores the new words. Only an uppercase key is translated,
 * so a custom title is shown as typed. Component keys that are missing from
 * the current (site) language are resolved from the administrator ini without
 * reloading that whole file into the shared Language object.
 *
 * @since  1.0.0
 */
final class LookupTitle
{
	/**
	 * Parsed administrator com_wendfold.ini strings, keyed by language tag.
	 *
	 * @var    array<string, array<string, string>>
	 * @since  1.0.0
	 */
	private static $adminStrings = [];

	/**
	 * Whether the stored title is a language key.
	 *
	 * @param   string  $title  Column value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isLanguageKey(string $title): bool
	{
		return preg_match('/^[A-Z][A-Z0-9_]+$/', $title) === 1;
	}

	/**
	 * Text to show for a stored title.
	 *
	 * @param   string  $title  Column value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function display(string $title): string
	{
		$title = trim($title);

		if ($title === '')
		{
			return '';
		}

		if (!self::isLanguageKey($title))
		{
			return $title;
		}

		if (!str_starts_with($title, 'COM_WENDFOLD_'))
		{
			return Text::_($title);
		}

		try
		{
			$language = Factory::getApplication()->getLanguage();
		}
		catch (\Throwable)
		{
			return Text::_($title);
		}

		if ($language->hasKey($title))
		{
			return $language->_($title);
		}

		$admin = self::adminComponentStrings($language->getTag());
		$key = strtoupper($title);

		if (isset($admin[$key]))
		{
			return $admin[$key];
		}

		return $language->_($title);
	}

	/**
	 * Administrator com_wendfold.ini strings for one language tag.
	 *
	 * Tries the requested tag, then falls back to en-GB when that file is
	 * missing. Results are cached per tag. An empty parse for a missing
	 * non-en-GB file is not cached until after the en-GB fallback runs.
	 *
	 * @param   string  $tag  Language tag such as en-GB or fr-FR.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	private static function adminComponentStrings(string $tag): array
	{
		if (isset(self::$adminStrings[$tag]))
		{
			return self::$adminStrings[$tag];
		}

		$strings = self::parseAdminIni($tag);

		if ($strings === [] && $tag !== 'en-GB')
		{
			$strings = self::adminComponentStrings('en-GB');
		}

		self::$adminStrings[$tag] = $strings;

		return $strings;
	}

	/**
	 * Parse administrator com_wendfold.ini paths for one language tag.
	 *
	 * Does not write the static cache: callers decide when to store results
	 * so a missing non-en-GB file can still fall back to en-GB.
	 *
	 * @param   string  $tag  Language tag.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	private static function parseAdminIni(string $tag): array
	{
		$candidates = [
			JPATH_ADMINISTRATOR . '/language/' . $tag . '/com_wendfold.ini',
			JPATH_ADMINISTRATOR . '/components/com_wendfold/language/' . $tag . '/com_wendfold.ini',
		];

		foreach ($candidates as $path)
		{
			$parsed = LanguageHelper::parseIniFile($path);

			if ($parsed !== [])
			{
				return $parsed;
			}
		}

		return [];
	}
}

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

use Joomla\CMS\Language\Text;

/**
 * Map a swallowed AdminModel duplicate-key failure to a translated message.
 *
 * @since  1.0.0
 */
final class SaveDuplicateKey
{
	/**
	 * Translated message when $error looks like a MySQL duplicate-key failure.
	 *
	 * @param   string|null  $error    Message from Model::getError() or an exception.
	 * @param   string       $langKey  Language key to show instead of the raw SQL text.
	 *
	 * @return  string|null  Translated message, or null when $error is not a duplicate key.
	 *
	 * @since   1.0.0
	 */
	public static function remap(?string $error, string $langKey): ?string
	{
		if ($error === null || $error === '')
		{
			return null;
		}

		if (!self::looksLikeDuplicate($error))
		{
			return null;
		}

		return Text::_($langKey);
	}

	/**
	 * Whether a throwable is a duplicate key (including wrapped drivers).
	 *
	 * @param   \Throwable  $error  Database or model exception.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function fromThrowable(\Throwable $error): bool
	{
		return DuplicateKey::is($error);
	}

	/**
	 * Language key for a member-profile duplicate-key failure.
	 *
	 * Uses the unique-key name from the driver message when present so a
	 * UNIQUE(user_id) clash is not reported as a member-number problem.
	 *
	 * @param   string|\Throwable|null  $error  Model getError() text or throwable.
	 *
	 * @return  string|null  Language key, or null when $error is not a duplicate key.
	 *
	 * @since   1.0.0
	 */
	public static function memberLangKey(string|\Throwable|null $error): ?string
	{
		if ($error instanceof \Throwable)
		{
			if (!DuplicateKey::is($error))
			{
				return null;
			}

			return self::memberLangKeyForConstraint(DuplicateKey::constraintName($error));
		}

		if ($error === null || $error === '' || !self::looksLikeDuplicate($error))
		{
			return null;
		}

		return self::memberLangKeyForConstraint(DuplicateKey::constraintName($error));
	}

	/**
	 * @param   string|null  $constraint  Unique-key name from the driver.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function memberLangKeyForConstraint(?string $constraint): string
	{
		$name = strtolower((string) $constraint);

		if ($name === 'idx_member_number' || str_ends_with($name, 'member_number'))
		{
			return 'COM_WENDFOLD_ERROR_MEMBER_NUMBER_EXISTS';
		}

		if ($name === 'idx_user_id' || str_ends_with($name, 'user_id'))
		{
			return 'COM_WENDFOLD_ERROR_MEMBER_USER_EXISTS';
		}

		return 'COM_WENDFOLD_ERROR_MEMBER_DUPLICATE';
	}

	/**
	 * @param   string  $error  Error text.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function looksLikeDuplicate(string $error): bool
	{
		if (stripos($error, 'Duplicate entry') !== false)
		{
			return true;
		}

		return (bool) preg_match('/\b1062\b/', $error)
			|| (str_contains($error, '23000') && stripos($error, 'Duplicate') !== false);
	}
}

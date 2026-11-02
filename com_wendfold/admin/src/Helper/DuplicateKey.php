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

/**
 * Detect MySQL / MariaDB duplicate-key failures.
 *
 * @since  1.0.0
 */
final class DuplicateKey
{
	/**
	 * Whether the throwable is a duplicate-key rejection.
	 *
	 * Matches driver error 1062, or SQLSTATE 23000 whose message mentions
	 * "Duplicate entry". Walks previous throwables when the driver wraps them.
	 *
	 * @param   \Throwable  $error  Database or driver exception.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function is(\Throwable $error): bool
	{
		$current = $error;

		while ($current instanceof \Throwable)
		{
			if ((int) $current->getCode() === 1062)
			{
				return true;
			}

			$code = (string) $current->getCode();
			$message = $current->getMessage();

			if ($code === '23000' && stripos($message, 'Duplicate entry') !== false)
			{
				return true;
			}

			$current = $current->getPrevious();
		}

		return false;
	}

	/**
	 * MySQL / MariaDB unique-key name from a duplicate-key error text.
	 *
	 * Matches messages such as `Duplicate entry 'x' for key 'idx_member_number'`.
	 * Returns the final segment when the driver prefixes a table name
	 * (`profiles.idx_user_id` → `idx_user_id`).
	 *
	 * @param   string|\Throwable|null  $error  Error text or throwable chain.
	 *
	 * @return  string|null  Constraint name, or null when not present.
	 *
	 * @since   1.0.0
	 */
	public static function constraintName(string|\Throwable|null $error): ?string
	{
		foreach (self::messages($error) as $message)
		{
			if (!preg_match("/for key ['`]?([^'`\\s]+)['`]?/i", $message, $matches))
			{
				continue;
			}

			$name = $matches[1];
			$dot = strrpos($name, '.');

			if ($dot !== false)
			{
				$name = substr($name, $dot + 1);
			}

			return $name !== '' ? $name : null;
		}

		return null;
	}

	/**
	 * @param   string|\Throwable|null  $error  Error text or throwable.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private static function messages(string|\Throwable|null $error): array
	{
		if ($error === null)
		{
			return [];
		}

		if (\is_string($error))
		{
			return $error === '' ? [] : [$error];
		}

		$messages = [];
		$current = $error;

		while ($current instanceof \Throwable)
		{
			$messages[] = $current->getMessage();
			$current = $current->getPrevious();
		}

		return $messages;
	}
}

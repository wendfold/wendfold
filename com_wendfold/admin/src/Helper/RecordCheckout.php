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
 * Pure checkout state rules used by admin edit and check-in.
 *
 * Mirrors Joomla Table semantics for the held-by-other decision and the
 * cleared column values after check-in, without touching the database.
 *
 * @since  1.0.0
 */
final class RecordCheckout
{
	/**
	 * Whether another user currently holds the edit lock.
	 *
	 * @param   integer  $checkedOut  User id in checked_out, or 0 when free.
	 * @param   integer  $userId      Current user id.
	 *
	 * @return  boolean  True when edit must be refused for this user.
	 *
	 * @since   1.0.0
	 */
	public static function isHeldByOther(int $checkedOut, int $userId): bool
	{
		return $checkedOut > 0 && $checkedOut !== $userId;
	}

	/**
	 * Column values after a successful check-in.
	 *
	 * @return  array{checked_out: null, checked_out_time: null}
	 *
	 * @since   1.0.0
	 */
	public static function cleared(): array
	{
		return [
			'checked_out'      => null,
			'checked_out_time' => null,
		];
	}

	/**
	 * Apply a checkout for $userId when the row is free or already held by them.
	 *
	 * @param   integer      $checkedOut  Current checked_out value.
	 * @param   integer      $userId      User requesting the lock.
	 * @param   string|null  $when        UTC datetime string to store.
	 *
	 * @return  array{ok: bool, checked_out: int|null, checked_out_time: string|null}
	 *
	 * @since   1.0.0
	 */
	public static function take(int $checkedOut, int $userId, ?string $when): array
	{
		if (self::isHeldByOther($checkedOut, $userId))
		{
			return [
				'ok'               => false,
				'checked_out'      => $checkedOut,
				'checked_out_time' => null,
			];
		}

		return [
			'ok'               => true,
			'checked_out'      => $userId,
			'checked_out_time' => $when,
		];
	}

	/**
	 * Clear the lock regardless of who held it (authorised check-in).
	 *
	 * @param   array{checked_out?: int|null, checked_out_time?: string|null}  $row  Row fields.
	 *
	 * @return  array{checked_out: null, checked_out_time: null}
	 *
	 * @since   1.0.0
	 */
	public static function release(array $row): array
	{
		return self::cleared();
	}
}

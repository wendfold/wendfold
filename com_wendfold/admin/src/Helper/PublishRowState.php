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

/**
 * Classifies a row before a publish / unpublish / archive / trash attempt.
 *
 * Core AdminModel::publish leaves already-at-state and checkout-skipped ids in
 * the primary-key list while Table::publish filters them in SQL, so callers
 * must decide counts from a re-read instead of trusting a non-empty $pks.
 *
 * @since  1.0.0
 */
final class PublishRowState
{
	/**
	 * Row needs a real published-state write.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const NEEDS_CHANGE = 'needs_change';

	/**
	 * Row is already at the target state and not checked out by another user.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const UNCHANGED = 'unchanged';

	/**
	 * Row is checked out by another user.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const CHECKED_OUT = 'checked_out';

	/**
	 * Row could not be loaded.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MISSING = 'missing';

	/**
	 * Classify from a loaded Table instance.
	 *
	 * @param   object   $table   Table with load/hasField/getColumnAlias.
	 * @param   integer  $pk      Primary key.
	 * @param   integer  $value   Target published state.
	 * @param   integer  $userId  Current user id.
	 *
	 * @return  string  One of the class constants.
	 *
	 * @since   1.0.0
	 */
	public static function classifyTable(object $table, int $pk, int $value, int $userId): string
	{
		if (!method_exists($table, 'load') || !$table->load($pk))
		{
			return self::MISSING;
		}

		$hasCheckedOut = method_exists($table, 'hasField')
			? (bool) $table->hasField('checked_out')
			: property_exists($table, 'checked_out');

		$checkedOut = 0;

		if ($hasCheckedOut)
		{
			$checkedOutField = method_exists($table, 'getColumnAlias')
				? (string) $table->getColumnAlias('checked_out')
				: 'checked_out';
			$checkedOut = (int) ($table->$checkedOutField ?? 0);
		}

		$publishedField = method_exists($table, 'getColumnAlias')
			? (string) $table->getColumnAlias('published')
			: 'published';
		$published = (int) ($table->$publishedField ?? ($value + 1));

		return self::classify($published, $checkedOut, $hasCheckedOut, $value, $userId);
	}

	/**
	 * Classify from already-read published / checkout values.
	 *
	 * @param   integer  $published      Current published value.
	 * @param   integer  $checkedOut     checked_out user id (0 when free).
	 * @param   boolean  $hasCheckedOut  Whether the table supports checkout.
	 * @param   integer  $value          Target published state.
	 * @param   integer  $userId         Current user id.
	 *
	 * @return  string  One of the class constants.
	 *
	 * @since   1.0.0
	 */
	public static function classify(
		int $published,
		int $checkedOut,
		bool $hasCheckedOut,
		int $value,
		int $userId
	): string {
		if ($hasCheckedOut && $checkedOut !== 0 && $checkedOut !== $userId)
		{
			return self::CHECKED_OUT;
		}

		if ($published === $value)
		{
			return self::UNCHANGED;
		}

		return self::NEEDS_CHANGE;
	}
}

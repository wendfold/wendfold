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

use Joomla\Database\DatabaseInterface;

/**
 * Named MySQL lock serialising CSV roster imports.
 *
 * Lock name is `<db prefix>wendfold_import`. Timeout 0 refuses a second
 * concurrent importer immediately.
 *
 * @since  1.0.0
 */
final class ImportLock
{
	/**
	 * Lock name including the table prefix.
	 *
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function name(DatabaseInterface $db): string
	{
		return $db->getPrefix() . 'wendfold_import';
	}

	/**
	 * Try to acquire the import lock without waiting.
	 *
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @return  boolean  True when this caller holds the lock.
	 *
	 * @since   1.0.0
	 */
	public static function acquire(DatabaseInterface $db): bool
	{
		$name = self::name($db);
		$result = $db->setQuery(
			'SELECT GET_LOCK(' . $db->quote($name) . ', 0)'
		)->loadResult();

		return (int) $result === 1;
	}

	/**
	 * Release the import lock when this connection holds it.
	 *
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function release(DatabaseInterface $db): void
	{
		$name = self::name($db);
		$db->setQuery(
			'SELECT RELEASE_LOCK(' . $db->quote($name) . ')'
		)->execute();
	}
}

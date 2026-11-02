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
use Joomla\Database\ParameterType;

/**
 * Lock one event row for the rest of the open transaction.
 *
 * Must be called inside an open transaction: a FOR UPDATE select in
 * autocommit releases the lock immediately. Joomla's driver has no public
 * transaction-depth getter, so callers and tests enforce that order.
 *
 * MySQL/MariaDB have no query-builder forUpdate(); the lock clause is
 * appended after the bound SELECT is built so bindings stay on the query.
 *
 * @since  1.0.0
 */
final class EventRowLock
{
	/**
	 * Select the event row with FOR UPDATE, or null when it is missing.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  object|null  Object with id, published, status, event_date, checked_out.
	 *
	 * @since   1.0.0
	 */
	public static function lock(DatabaseInterface $db, int $eventId): ?object
	{
		if ($eventId < 1)
		{
			return null;
		}

		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('published'),
				$db->quoteName('status'),
				$db->quoteName('event_date'),
				$db->quoteName('checked_out'),
				]
			)
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $eventId, ParameterType::INTEGER);

		$query->setQuery((string) $query . ' FOR UPDATE');
		$row = $db->setQuery($query)->loadObject();

		return $row ?: null;
	}
}

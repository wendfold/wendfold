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
 * Single conditional UPDATE that moves an event to Cancelled.
 *
 * Only the first writer that changes status away from Cancelled reports
 * affected rows. A repeat cancel, double submit, or race that finds the
 * row already Cancelled returns false so cancel notices send once per
 * transition. No extra schema column is required.
 *
 * @since  1.0.0
 */
final class EventCancelTransition
{
	/**
	 * Event status: Cancelled.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public const STATUS_CANCELLED = 3;

	/**
	 * Set status to Cancelled when it is not already Cancelled.
	 *
	 * @param   DatabaseInterface  $db       Database.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  boolean  True when this call changed the row.
	 *
	 * @since   1.0.0
	 */
	public static function apply(DatabaseInterface $db, int $eventId): bool
	{
		if ($eventId < 1)
		{
			return false;
		}

		$cancelled = self::STATUS_CANCELLED;
		$already   = self::STATUS_CANCELLED;

		$query = $db->createQuery()
			->update($db->quoteName('#__wendfold_events'))
			->set($db->quoteName('status') . ' = :cancelled')
			->where($db->quoteName('id') . ' = :id')
			->where($db->quoteName('status') . ' <> :already')
			->bind(':cancelled', $cancelled, ParameterType::INTEGER)
			->bind(':id', $eventId, ParameterType::INTEGER)
			->bind(':already', $already, ParameterType::INTEGER);

		$db->setQuery($query)->execute();

		return (int) $db->getAffectedRows() > 0;
	}
}

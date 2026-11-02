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
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;

/**
 * Hard-delete every child row that points at an event.
 *
 * The caller owns the database transaction (and the event-row delete). This
 * helper is the only cascade used by EventModel::delete so single and batch
 * deletes share one path. Metric entries go through MetricService so member
 * totals stay consistent with the ledger.
 *
 * Child tables are every install-SQL table with an event_id column, except
 * #__wendfold_events itself (parent_event_id is a sibling link, not a child
 * row to purge here). Reminder logs key events in reminder_key text and are
 * not event_id children.
 *
 * @since  1.0.0
 */
final class EventDeleteCascade
{
	/**
	 * Non-metric child tables keyed by a short log name, in delete order.
	 *
	 * Group members before groups; volunteers before roles. Metric entries
	 * are not listed here: they are removed through MetricService.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	public static function childTables(): array
	{
		return [
			'attendance'              => '#__wendfold_attendance',
			'rsvps'                   => '#__wendfold_rsvps',
			'event_group_members'     => '#__wendfold_event_group_members',
			'event_groups'            => '#__wendfold_event_groups',
			'event_volunteers'        => '#__wendfold_event_volunteers',
			'event_volunteer_roles'   => '#__wendfold_event_volunteer_roles',
			'event_trip_participants' => '#__wendfold_event_trip_participants',
			'event_metric_credits'    => '#__wendfold_event_metric_credits',
		];
	}

	/**
	 * Hard-delete metric entries and every other child row for one event.
	 *
	 * Does not delete the event row and does not start a transaction. The
	 * caller must wrap this with the event-row delete in one transaction.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   MetricService      $service  Metric writer.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function purge(DatabaseInterface $db, MetricService $service, int $eventId): void
	{
		if ($eventId < 1)
		{
			return;
		}

		$service->deleteEventEntriesJoining($eventId);
		self::deleteChildTableRows($db, $eventId);
	}

	/**
	 * Delete rows in each non-metric child table for one event.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function deleteChildTableRows(DatabaseInterface $db, int $eventId): void
	{
		foreach (self::childTables() as $table)
		{
			$query = $db->createQuery()
				->delete($db->quoteName($table))
				->where($db->quoteName('event_id') . ' = :eventId')
				->bind(':eventId', $eventId, ParameterType::INTEGER);
			$db->setQuery($query)->execute();
		}
	}
}

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
 * Resolve conditional event-status UPDATEs when MySQL reports 0 affected rows.
 *
 * getAffectedRows() counts only changed values, so setting status to its
 * current value returns 0 even when WHERE status IN (...) matched. Callers
 * must hold EventRowLock on the event for the re-read.
 *
 * @since  1.0.0
 */
final class EventStatusTransition
{
	/**
	 * Conditional UPDATE changed a row, or re-read status already equals target.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const OK = 'ok';

	/**
	 * Zero-row UPDATE left the event in an allowed source status (not the target).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const DB_ERROR = 'db_error';

	/**
	 * Event is missing or no longer in an allowed source status.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const EVENT_CLOSED = 'event_closed';

	/**
	 * Decide the outcome of a conditional status UPDATE.
	 *
	 * @param   DatabaseInterface  $db                     Database driver.
	 * @param   integer            $eventId                Event id (already locked).
	 * @param   integer            $affectedRows           Driver affected-row count.
	 * @param   integer            $targetStatus           Status the UPDATE tried to set.
	 * @param   integer[]          $allowedSourceStatuses  Statuses the WHERE clause allowed.
	 *
	 * @return  string  self::OK, self::DB_ERROR, or self::EVENT_CLOSED.
	 *
	 * @since   1.0.0
	 */
	public static function resolve(
		DatabaseInterface $db,
		int $eventId,
		int $affectedRows,
		int $targetStatus,
		array $allowedSourceStatuses
	): string
	{
		if ($affectedRows === 1)
		{
			return self::OK;
		}

		$locked = EventRowLock::lock($db, $eventId);

		if ($locked === null)
		{
			return self::EVENT_CLOSED;
		}

		$status = (int) $locked->status;

		if ($status === $targetStatus)
		{
			return self::OK;
		}

		if (\in_array($status, $allowedSourceStatuses, true))
		{
			return self::DB_ERROR;
		}

		return self::EVENT_CLOSED;
	}
}

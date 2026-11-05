<?php

/**
 * Wendfold - Site attendance event published-state gate.
 *
 * getEvent() (after #100) loads every non-trashed event so credit sync and
 * model helpers can see unpublished and archived rows. Site roll call and
 * road-captain attendance pages and their save tasks still open only for
 * published and archived events: unpublished is refused with a specific
 * message; trashed and missing keep the generic invalid-event message.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Shared published-state rule for meeting and RC attendance pages and saves.
 *
 * @since  1.0.0
 */
abstract class AttendanceEventAccess
{
	/**
	 * Published.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const PUBLISHED = 1;

	/**
	 * Archived. Attendance still counts; officers may correct history.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const ARCHIVED = 2;

	/**
	 * Unpublished. Attendance is recorded elsewhere with 0 credit; site pages stay closed.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const UNPUBLISHED = 0;

	/**
	 * Trashed.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const TRASHED = -2;

	/**
	 * Language key when the event exists but is unpublished.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const KEY_NOT_PUBLISHED = 'COM_WENDFOLD_EVENT_NOT_PUBLISHED';

	/**
	 * Language key for missing, trashed, or otherwise unusable events.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const KEY_INVALID = 'COM_WENDFOLD_ERROR_INVALID_EVENT';

	/**
	 * Published states that may open roll call / RC attendance and accept saves.
	 *
	 * @return  int[]
	 *
	 * @since   1.0.0
	 */
	public static function allowedStates(): array
	{
		return [self::PUBLISHED, self::ARCHIVED];
	}

	/**
	 * Whether this published value may open attendance pages and save tasks.
	 *
	 * @param   integer|null  $published  Event published column, or null when missing.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function allows(?int $published): bool
	{
		return $published === self::PUBLISHED || $published === self::ARCHIVED;
	}

	/**
	 * Whether the site router may resolve this published value for attendance views.
	 *
	 * Unpublished is included so the attendance gate can show its own message.
	 * Trashed and missing stay unresolved.
	 *
	 * @param   integer|null  $published  Event published column, or null when missing.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isRouterResolvable(?int $published): bool
	{
		return $published === self::PUBLISHED
			|| $published === self::ARCHIVED
			|| $published === self::UNPUBLISHED;
	}

	/**
	 * Language key when attendance is blocked, or null when allowed.
	 *
	 * @param   integer|null  $published  Event published column, or null when missing.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function denialKey(?int $published): ?string
	{
		if (self::allows($published))
		{
			return null;
		}

		if ($published === self::UNPUBLISHED)
		{
			return self::KEY_NOT_PUBLISHED;
		}

		return self::KEY_INVALID;
	}

	/**
	 * Load the event published column, or null when the id does not exist.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public static function loadPublished(DatabaseInterface $db, int $eventId): ?int
	{
		if ($eventId <= 0)
		{
			return null;
		}

		$published = $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('published'))
				->from($db->quoteName('#__wendfold_events'))
				->where($db->quoteName('id') . ' = :eventId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
		)->loadResult();

		if ($published === null || $published === false)
		{
			return null;
		}

		return (int) $published;
	}

	/**
	 * Denial language key for an event id, or null when attendance is allowed.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function denialKeyForEvent(DatabaseInterface $db, int $eventId): ?string
	{
		return self::denialKey(self::loadPublished($db, $eventId));
	}
}

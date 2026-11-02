<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * MySQL and MariaDB writer for metric_entries.
 *
 * Upserts use INSERT ... SELECT ... ON DUPLICATE KEY UPDATE with a select
 * alias. There is no VALUES() function and no INSERT IGNORE.
 *
 * @since  1.0.0
 */
final class SqlMetricEntryStore implements MetricEntryStore
{
	/**
	 * Bound integers for the statement currently being built.
	 *
	 * @var    array<string, int>
	 * @since  1.0.0
	 */
	private array $ints = [];

	/**
	 * Bound strings for the statement currently being built.
	 *
	 * @var    array<string, string>
	 * @since  1.0.0
	 */
	private array $strings = [];

	/**
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $actorId  User id stamped on modified_by.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private DatabaseInterface $db, private int $actorId)
	{
	}

	/**
	 * SQL text for one upsert.
	 *
	 * Null source or event ids are SQL NULL, not a bound zero.
	 *
	 * @param   DatabaseInterface  $db          Driver, used to quote names.
	 * @param   boolean            $nullSource  True when source_id is null.
	 * @param   boolean            $nullEvent   True when event_id is null.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function upsertSql(DatabaseInterface $db, bool $nullSource, bool $nullEvent): string
	{
		$source = $nullSource ? 'NULL' : ':sourceId';
		$event = $nullEvent ? 'NULL' : ':eventId';
		$src = $db->quoteName('src');

		$assignments = [];

		foreach (['profile_id', 'entry_date', 'value', 'event_id', 'published', 'note', 'modified', 'modified_by'] as $column)
		{
			$assignments[] = $db->quoteName($column) . ' = ' . $src . '.' . $db->quoteName($column);
		}

		return 'INSERT INTO ' . $db->quoteName('#__wendfold_metric_entries')
			. ' ('
			. $db->quoteName('profile_id') . ', '
			. $db->quoteName('metric_id') . ', '
			. $db->quoteName('entry_date') . ', '
			. $db->quoteName('value') . ', '
			. $db->quoteName('event_id') . ', '
			. $db->quoteName('source_type') . ', '
			. $db->quoteName('source_id') . ', '
			. $db->quoteName('published') . ', '
			. $db->quoteName('note') . ', '
			. $db->quoteName('created') . ', '
			. $db->quoteName('created_by') . ', '
			. $db->quoteName('modified') . ', '
			. $db->quoteName('modified_by')
			. ') SELECT '
			. $src . '.' . $db->quoteName('profile_id') . ', '
			. $src . '.' . $db->quoteName('metric_id') . ', '
			. $src . '.' . $db->quoteName('entry_date') . ', '
			. $src . '.' . $db->quoteName('value') . ', '
			. $src . '.' . $db->quoteName('event_id') . ', '
			. $src . '.' . $db->quoteName('source_type') . ', '
			. $src . '.' . $db->quoteName('source_id') . ', '
			. $src . '.' . $db->quoteName('published') . ', '
			. $src . '.' . $db->quoteName('note') . ', '
			. $src . '.' . $db->quoteName('created') . ', '
			. $src . '.' . $db->quoteName('created_by') . ', '
			. $src . '.' . $db->quoteName('modified') . ', '
			. $src . '.' . $db->quoteName('modified_by')
			. ' FROM (SELECT '
			. ':profileId AS ' . $db->quoteName('profile_id') . ', '
			. ':metricId AS ' . $db->quoteName('metric_id') . ', '
			. ':entryDate AS ' . $db->quoteName('entry_date') . ', '
			. ':entryValue AS ' . $db->quoteName('value') . ', '
			. $event . ' AS ' . $db->quoteName('event_id') . ', '
			. ':sourceType AS ' . $db->quoteName('source_type') . ', '
			. $source . ' AS ' . $db->quoteName('source_id') . ', '
			. ':published AS ' . $db->quoteName('published') . ', '
			. ':note AS ' . $db->quoteName('note') . ', '
			. ':created AS ' . $db->quoteName('created') . ', '
			. ':createdBy AS ' . $db->quoteName('created_by') . ', '
			. ':modified AS ' . $db->quoteName('modified') . ', '
			. ':modifiedBy AS ' . $db->quoteName('modified_by')
			. ') AS ' . $src
			. ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function openTransactionDepth(): int
	{
		$db = $this->db;

		if (!property_exists($db, 'transactionDepth'))
		{
			return 0;
		}

		$depth = new \ReflectionProperty($db, 'transactionDepth');

		return (int) $depth->getValue($db);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function begin(): void
	{
		$this->db->transactionStart(true);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function commit(): void
	{
		$this->db->transactionCommit(true);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function rollback(): void
	{
		$this->db->transactionRollback(true);
	}

	/**
	 * @param   array  $row  Entry columns.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upsert(array $row): void
	{
		$sourceId = $row['source_id'];
		$eventId = $row['event_id'];
		$nullSource = $sourceId === null;
		$nullEvent = $eventId === null;
		$now = Factory::getDate()->toSql();
		$actor = (int) $row['actor_id'];

		$query = $this->db->createQuery();
		$this->ints = [];
		$this->strings = [];
		$this->bindInt($query, ':profileId', (int) $row['profile_id']);
		$this->bindInt($query, ':metricId', (int) $row['metric_id']);
		$this->bindString($query, ':entryDate', (string) $row['entry_date']);
		$this->bindString($query, ':entryValue', (string) $row['value']);
		$this->bindInt($query, ':sourceType', (int) $row['source_type']);
		$this->bindInt($query, ':published', (int) $row['published']);
		$this->bindString($query, ':note', (string) $row['note']);
		$this->bindString($query, ':created', $now);
		$this->bindInt($query, ':createdBy', $actor);
		$this->bindString($query, ':modified', $now);
		$this->bindInt($query, ':modifiedBy', $actor);

		if (!$nullSource)
		{
			$this->bindInt($query, ':sourceId', (int) $sourceId);
		}

		if (!$nullEvent)
		{
			$this->bindInt($query, ':eventId', (int) $eventId);
		}

		$query->setQuery(self::upsertSql($this->db, $nullSource, $nullEvent));
		$this->db->setQuery($query)->execute();
	}

	/**
	 * @param   integer  $sourceType  MetricSource value.
	 * @param   integer  $sourceId    Source primary key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function trashSource(int $sourceType, int $sourceId): bool
	{
		$db = $this->db;

		return $this->updatePublished(
			$db->quoteName('source_type') . ' = :sourceType AND '
			. $db->quoteName('source_id') . ' = :sourceId AND '
			. $db->quoteName('published') . ' <> :alreadyTrashed',
			[
				'sourceType'     => $sourceType,
				'sourceId'       => $sourceId,
				'alreadyTrashed' => -2,
			],
			-2,
			null
		);
	}

	/**
	 * @param   integer  $eventId       Event id.
	 * @param   integer  $attendanceId  Attendance id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function trashAttendanceSource(int $eventId, int $attendanceId): bool
	{
		$db = $this->db;

		return $this->updatePublished(
			$db->quoteName('source_type') . ' = :sourceType AND '
			. $db->quoteName('source_id') . ' = :sourceId AND '
			. $db->quoteName('event_id') . ' = :eventId AND '
			. $db->quoteName('published') . ' <> :alreadyTrashed',
			[
				'sourceType'     => MetricSource::Attendance->value,
				'sourceId'       => $attendanceId,
				'eventId'        => $eventId,
				'alreadyTrashed' => -2,
			],
			-2,
			null
		);
	}

	/**
	 * @param   integer    $eventId    Event id.
	 * @param   integer[]  $metricIds  Metrics that remain credited.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function trashAttendanceMetricsExcept(int $eventId, array $metricIds): void
	{
		$ids = [];

		foreach ($metricIds as $metricId)
		{
			$metricId = (int) $metricId;

			if ($metricId > 0)
			{
				$ids[$metricId] = $metricId;
			}
		}

		$db = $this->db;
		$where = $db->quoteName('source_type') . ' = :sourceType AND ' . $db->quoteName('event_id') . ' = :eventId';
		$ints = [
			'sourceType' => MetricSource::Attendance->value,
			'eventId'    => $eventId,
		];

		if ($ids !== [])
		{
			$names = [];

			foreach (array_values($ids) as $index => $metricId)
			{
				$name = ':keep' . $index;
				$ints['keep' . $index] = $metricId;
				$names[] = $name;
			}

			$where .= ' AND ' . $db->quoteName('metric_id') . ' NOT IN (' . implode(', ', $names) . ')';
		}

		$this->updatePublished($where, $ints, -2, null);
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteByEvent(int $eventId): void
	{
		$db = $this->db;
		$query = $db->createQuery()
			->delete($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('event_id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);
		$db->setQuery($query)->execute();
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	public function profileIdsForEvent(int $eventId): array
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select('DISTINCT ' . $db->quoteName('profile_id'))
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('event_id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);

		return array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function deleteOrphanedAttendanceEntries(): int
	{
		$db = $this->db;
		$sourceType = MetricSource::Attendance->value;
		$entries = $db->quoteName('#__wendfold_metric_entries');
		$events = $db->quoteName('#__wendfold_events');
		$query = $db->createQuery()
			->delete($entries)
			->where($db->quoteName('source_type') . ' = :sourceType')
			->where($db->quoteName('event_id') . ' IS NOT NULL')
			->where(
				'NOT EXISTS (SELECT 1 FROM ' . $events . ' AS ' . $db->quoteName('ev')
				. ' WHERE ' . $db->quoteName('ev.id') . ' = ' . $entries . '.' . $db->quoteName('event_id') . ')'
			)
			->bind(':sourceType', $sourceType, ParameterType::INTEGER);
		$db->setQuery($query)->execute();

		return $db->getAffectedRows();
	}

	/**
	 * @param   integer  $sourceType  MetricSource value.
	 * @param   integer  $sourceId    Source primary key.
	 * @param   integer  $published   Target published value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function setSourcePublished(int $sourceType, int $sourceId, int $published): bool
	{
		$db = $this->db;

		return $this->updatePublished(
			$db->quoteName('source_type') . ' = :sourceType AND '
			. $db->quoteName('source_id') . ' = :sourceId AND '
			. $db->quoteName('published') . ' <> :targetPublished',
			[
				'sourceType'      => $sourceType,
				'sourceId'        => $sourceId,
				'targetPublished' => $published,
			],
			$published,
			null
		);
	}

	/**
	 * @param   integer  $attendanceId  Attendance id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function attendance(int $attendanceId): ?object
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select([
				$db->quoteName('a.id'),
				$db->quoteName('a.profile_id'),
				$db->quoteName('a.event_id'),
				$db->quoteName('e.event_date'),
				]
			)
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_events', 'e')
				. ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.event_id')
			)
			->where($db->quoteName('a.id') . ' = :attendanceId')
			->bind(':attendanceId', $attendanceId, ParameterType::INTEGER);

		$row = $db->setQuery($query)->loadObject();

		return $row ?: null;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function eventCredits(int $eventId): array
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select([
				$db->quoteName('metric_id'),
				$db->quoteName('value'),
				]
			)
			->from($db->quoteName('#__wendfold_event_metric_credits'))
			->where($db->quoteName('event_id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function eventDate(int $eventId): ?string
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select($db->quoteName('event_date'))
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);
		$value = $db->setQuery($query)->loadResult();

		if (!\is_string($value) || !MetricPeriod::isDay(substr($value, 0, 10)))
		{
			return null;
		}

		return substr($value, 0, 10);
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function eventPublished(int $eventId): ?int
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select($db->quoteName('published'))
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);
		$value = $db->setQuery($query)->loadResult();

		if ($value === null || $value === false)
		{
			return null;
		}

		return (int) $value;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function eventStatus(int $eventId): ?int
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select($db->quoteName('status'))
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);
		$value = $db->setQuery($query)->loadResult();

		if ($value === null || $value === false)
		{
			return null;
		}

		return (int) $value;
	}

	/**
	 * @param   integer  $sourceType  MetricSource value.
	 * @param   integer  $sourceId    Source primary key.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	public function profileIdsForSource(int $sourceType, int $sourceId): array
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select('DISTINCT ' . $db->quoteName('profile_id'))
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('source_type') . ' = :sourceType')
			->where($db->quoteName('source_id') . ' = :sourceId')
			->bind(':sourceType', $sourceType, ParameterType::INTEGER)
			->bind(':sourceId', $sourceId, ParameterType::INTEGER);

		return array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function eventAttendances(int $eventId): array
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('profile_id'),
				]
			)
			->from($db->quoteName('#__wendfold_attendance'))
			->where($db->quoteName('event_id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Update published, modified, and modified_by for the rows the where clause names.
	 *
	 * @param   string              $where       SQL fragment using bound names.
	 * @param   array<string, int>  $conditions  Bound integers, without the leading colon.
	 * @param   integer             $published   New published value.
	 * @param   string|null         $note        Replacement note, or null to leave it.
	 *
	 * @return  boolean  True when at least one row changed.
	 *
	 * @since   1.0.0
	 */
	private function updatePublished(string $where, array $conditions, int $published, ?string $note): bool
	{
		$db = $this->db;
		$now = Factory::getDate()->toSql();
		$actor = $this->actorId;

		$query = $db->createQuery();
		$this->ints = [];
		$this->strings = [];
		$this->bindInt($query, ':published', $published);
		$this->bindString($query, ':modified', $now);
		$this->bindInt($query, ':modifiedBy', $actor);

		$sql = 'UPDATE ' . $db->quoteName('#__wendfold_metric_entries')
			. ' SET ' . $db->quoteName('published') . ' = :published, '
			. $db->quoteName('modified') . ' = :modified, '
			. $db->quoteName('modified_by') . ' = :modifiedBy';

		if ($note !== null)
		{
			$this->bindString($query, ':note', $note);
			$sql .= ', ' . $db->quoteName('note') . ' = :note';
		}

		foreach ($conditions as $name => $value)
		{
			$this->bindInt($query, ':' . $name, $value);
		}

		$query->setQuery($sql . ' WHERE ' . $where);
		$db->setQuery($query)->execute();

		return $db->getAffectedRows() > 0;
	}

	/**
	 * Bind an integer on the query being built.
	 *
	 * @param   object   $query  Query with bind().
	 * @param   string   $name   Placeholder, including the colon.
	 * @param   integer  $value  Value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function bindInt(object $query, string $name, int $value): void
	{
		$key = ltrim($name, ':');
		$this->ints[$key] = $value;
		$query->bind($name, $this->ints[$key], ParameterType::INTEGER);
	}

	/**
	 * Bind a string on the query being built.
	 *
	 * @param   object  $query  Query with bind().
	 * @param   string  $name   Placeholder, including the colon.
	 * @param   string  $value  Value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function bindString(object $query, string $name, string $value): void
	{
		$key = ltrim($name, ':');
		$this->strings[$key] = $value;
		$query->bind($name, $this->strings[$key], ParameterType::STRING);
	}
}

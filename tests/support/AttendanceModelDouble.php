<?php

/**
 * Wendfold - AttendanceModel double for list-query tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\Model;

if (!\class_exists(ListModel::class, false))
{
	/**
	 * Stand-in so AttendanceModel can load without Joomla.
	 *
	 * @since  1.0.0
	 */
	class ListModel
	{
		/**
		 * @param   array  $config  Unused.
		 *
		 * @since   1.0.0
		 */
		public function __construct($config = [])
		{
		}
	}
}

namespace Wendfold\Tests\Support;

/**
 * Input bag for attendance list event_id.
 *
 * @since  1.0.0
 */
final class AttendanceInputDouble
{
	/**
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $eventId;

	/**
	 * @param   integer  $eventId  Event primary key.
	 *
	 * @since   1.0.0
	 */
	public function __construct(int $eventId = 0)
	{
		$this->eventId = $eventId;
	}

	/**
	 * @param   string   $name     Input name.
	 * @param   integer  $default  Default.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getInt(string $name, int $default = 0): int
	{
		if ($name === 'event_id')
		{
			return $this->eventId;
		}

		return $default;
	}
}

/**
 * Application double exposing getInput().
 *
 * @since  1.0.0
 */
final class AttendanceAppDouble
{
	/**
	 * @var    AttendanceInputDouble
	 * @since  1.0.0
	 */
	private AttendanceInputDouble $input;

	/**
	 * @param   integer  $eventId  Event primary key.
	 *
	 * @since   1.0.0
	 */
	public function __construct(int $eventId = 0)
	{
		$this->input = new AttendanceInputDouble($eventId);
	}

	/**
	 * @return  AttendanceInputDouble
	 *
	 * @since   1.0.0
	 */
	public function getInput(): AttendanceInputDouble
	{
		return $this->input;
	}
}

/**
 * Database stub that returns AttendanceQueryStub instances.
 *
 * @since  1.0.0
 */
final class AttendanceDatabaseStub
{
	/**
	 * @return  AttendanceQueryStub
	 *
	 * @since   1.0.0
	 */
	public function createQuery(): AttendanceQueryStub
	{
		return new AttendanceQueryStub;
	}

	/**
	 * @param   string       $name  Name.
	 * @param   string|null  $as    Alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null): string
	{
		if ($as !== null && $as !== '')
		{
			return $name . ' AS ' . $as;
		}

		return (string) $name;
	}
}

/**
 * Query stub that records SELECT, FROM, JOIN, WHERE, and ORDER.
 *
 * @since  1.0.0
 */
final class AttendanceQueryStub
{
	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $select = [];

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $from = '';

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $joins = [];

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $where = [];

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $order = [];

	/**
	 * @param   mixed  $columns  Columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns): self
	{
		foreach ((array) $columns as $column)
		{
			$this->select[] = (string) $column;
		}

		return $this;
	}

	/**
	 * @param   string  $table  Table.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function from($table): self
	{
		$this->from = (string) $table;

		return $this;
	}

	/**
	 * @param   string  $condition  Join SQL.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function leftJoin($condition): self
	{
		$this->joins[] = (string) $condition;

		return $this;
	}

	/**
	 * @param   mixed   $condition  Condition.
	 * @param   string  $glue       Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function where($condition, $glue = 'AND'): self
	{
		$this->where[] = (string) $condition;

		return $this;
	}

	/**
	 * @param   string  $columns  Order expression.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function order($columns): self
	{
		$this->order[] = (string) $columns;

		return $this;
	}

	/**
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public function selectedColumns(): array
	{
		return $this->select;
	}

	/**
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public function joinedTables(): array
	{
		return $this->joins;
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function __toString(): string
	{
		$joinSql = $this->joins === [] ? '' : ' LEFT JOIN ' . implode(' LEFT JOIN ', $this->joins);

		return 'SELECT ' . implode(', ', $this->select)
			. ' FROM ' . $this->from
			. $joinSql
			. ($this->where === [] ? '' : ' WHERE ' . implode(' AND ', $this->where))
			. ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order));
	}
}

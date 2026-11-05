<?php

/**
 * Wendfold - ActivitiesModel double for list-query tests.
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
	 * Stand-in so ActivitiesModel / MyactivitiesModel can load without Joomla.
	 *
	 * @since  1.0.0
	 */
	class ListModel
	{
		/**
		 * Filters the next populateState call copies into state.
		 *
		 * @var    array<string, mixed>
		 * @since  1.0.0
		 */
		public static array $incomingFilters = [];

		/**
		 * @var    string
		 * @since  1.0.0
		 */
		public $context = 'com_wendfold.myactivities';

		/**
		 * @var    object
		 * @since  1.0.0
		 */
		protected $state;

		/**
		 * @param   array  $config  Unused.
		 *
		 * @since   1.0.0
		 */
		public function __construct($config = [])
		{
			$this->state = new \Joomla\Registry\Registry;
		}

		/**
		 * @param   string|null  $key      State key.
		 * @param   mixed        $default  Default.
		 *
		 * @return  mixed
		 *
		 * @since   1.0.0
		 */
		public function getState($key = null, $default = null)
		{
			if ($key === null)
			{
				return $this->state;
			}

			return $this->state->get((string) $key, $default);
		}

		/**
		 * @param   string  $key    State key.
		 * @param   mixed   $value  Value.
		 *
		 * @return  mixed
		 *
		 * @since   1.0.0
		 */
		public function setState($key, $value = null)
		{
			$this->state->set((string) $key, $value);

			return $value;
		}

		/**
		 * @param   string|null  $ordering   Unused.
		 * @param   string|null  $direction  Unused.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		protected function populateState($ordering = null, $direction = null)
		{
			foreach (self::$incomingFilters as $name => $value)
			{
				$this->setState('filter.' . $name, $value);
			}
		}

		/**
		 * @param   string  $id  Store id seed.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		protected function getStoreId($id = '')
		{
			return \md5($id);
		}
	}
}

namespace Wendfold\Tests\Support;

/**
 * Database stub that returns ActivitiesQueryStub instances.
 *
 * @since  1.0.0
 */
final class ActivitiesDatabaseStub
{
	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  ActivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false): ActivitiesQueryStub
	{
		return new ActivitiesQueryStub;
	}

	/**
	 * Create a new query object.
	 *
	 * @return  ActivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function createQuery(): ActivitiesQueryStub
	{
		return $this->getQuery(true);
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

	/**
	 * @param   string   $text   Text.
	 * @param   boolean  $extra  Unused.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function escape($text, $extra = false): string
	{
		return addcslashes((string) $text, '%_');
	}

	/**
	 * @param   string|string[]  $text    Text.
	 * @param   boolean          $escape  Unused.
	 *
	 * @return  string|string[]
	 *
	 * @since   1.0.0
	 */
	public function quote($text, $escape = true)
	{
		if (\is_array($text))
		{
			return array_map(static fn ($part) => "'" . str_replace("'", "''", (string) $part) . "'", $text);
		}

		return "'" . str_replace("'", "''", (string) $text) . "'";
	}
}

/**
 * Query stub that records SELECT, WHERE, ORDER, and binds for count checks.
 *
 * @since  1.0.0
 */
final class ActivitiesQueryStub
{
	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $select = [];

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
	 * @var    string
	 * @since  1.0.0
	 */
	private string $from = '';

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $join = [];

	/**
	 * @var    array<string, mixed>
	 * @since  1.0.0
	 */
	public array $bound = [];

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
	 * @param   string       $type       Join type.
	 * @param   string       $table      Join SQL.
	 * @param   string|null  $condition  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function join($type, $table, $condition = null): self
	{
		$this->join[] = strtoupper((string) $type) . ' ' . (string) $table;

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
	 * @param   string  $name   Placeholder.
	 * @param   mixed   $value  Bound value.
	 * @param   mixed   $type   Unused type.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function bind($name, &$value, $type = null): self
	{
		$this->bound[(string) $name] = $value;

		return $this;
	}

	/**
	 * @param   string|null  $clause  Clause name, or null for all.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function clear($clause = null): self
	{
		if ($clause === null || $clause === 'select')
		{
			$this->select = [];
		}

		if ($clause === null || $clause === 'order')
		{
			$this->order = [];
		}

		return $this;
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function __toString(): string
	{
		return 'SELECT ' . implode(', ', $this->select)
			. ' FROM ' . $this->from
			. ($this->join === [] ? '' : ' ' . implode(' ', $this->join))
			. ($this->where === [] ? '' : ' WHERE ' . implode(' AND ', $this->where))
			. ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order));
	}
}

<?php

/**
 * Wendfold - MyactivitiesModel doubles for list and totals tests.
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
	 * Stand-in so MyactivitiesModel can load without Joomla.
	 *
	 * Mimics ListModel filter copying: populateState writes every key from
	 * {@see ListModel::$incomingFilters} into filter.* state (request/session).
	 *
	 * @since  1.0.0
	 */
	class ListModel
	{
		/**
		 * Filters the next populateState call copies into state (forged request/session).
		 *
		 * @var    array<string, mixed>
		 * @since  1.0.0
		 */
		public static array $incomingFilters = [];

		/**
		 * Model context used for user-state keys.
		 *
		 * @var    string
		 * @since  1.0.0
		 */
		public $context = 'com_wendfold.myactivities';

		/**
		 * State bag.
		 *
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
		 * Copy incoming filters into state, matching Joomla ListModel behaviour.
		 *
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

namespace Joomla\CMS;

if (!\class_exists(Factory::class, false))
{
	/**
	 * Factory stand-in for current-user resolution.
	 *
	 * @since  1.0.0
	 */
	class Factory
	{
		/**
		 * @var    object|null
		 * @since  1.0.0
		 */
		public static $application;

		/**
		 * @return  object
		 *
		 * @since   1.0.0
		 */
		public static function getApplication()
		{
			return self::$application;
		}
	}
}

namespace Wendfold\Tests\Support;

use Joomla\Database\DatabaseInterface;

/**
 * Application double with identity and user-state bag.
 *
 * @since  1.0.0
 */
final class MyactivitiesAppDouble
{
	/**
	 * @var    object
	 * @since  1.0.0
	 */
	private object $user;

	/**
	 * @var    array<string, mixed>
	 * @since  1.0.0
	 */
	public array $userState = [];

	/**
	 * @param   object  $user  Current identity.
	 *
	 * @since   1.0.0
	 */
	public function __construct(object $user)
	{
		$this->user = $user;
	}

	/**
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function getIdentity(): object
	{
		return $this->user;
	}

	/**
	 * @param   string  $key      Offset.
	 * @param   mixed   $default  Default when missing.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $default;
	}

	/**
	 * @param   string  $key    User-state key.
	 * @param   mixed   $value  Value.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function setUserState(string $key, mixed $value = null): mixed
	{
		if ($value === null)
		{
			unset($this->userState[$key]);
		}
		else
		{
			$this->userState[$key] = $value;
		}

		return $value;
	}

	/**
	 * @param   string  $key      User-state key.
	 * @param   mixed   $default  Default.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function getUserState(string $key, mixed $default = null): mixed
	{
		return $this->userState[$key] ?? $default;
	}
}

/**
 * Database stub that records metric list queries and computes published totals.
 *
 * @since  1.0.0
 */
final class MyactivitiesDatabaseStub implements DatabaseInterface
{
	/**
	 * Metric entry fixtures.
	 *
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	public array $entries = [];

	/**
	 * Metric definition fixtures.
	 *
	 * @var    list<object>
	 * @since  1.0.0
	 */
	public array $definitions = [];

	/**
	 * Profile rows keyed by user id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $profilesByUserId = [];

	/**
	 * Last query prepared for setQuery.
	 *
	 * @var    MyactivitiesQueryStub|null
	 * @since  1.0.0
	 */
	private ?MyactivitiesQueryStub $pending = null;

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  MyactivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false): MyactivitiesQueryStub
	{
		return new MyactivitiesQueryStub;
	}

	/**
	 * Create a new query object.
	 *
	 * @return  MyactivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function createQuery(): MyactivitiesQueryStub
	{
		return $this->getQuery(true);
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

	/**
	 * @param   string|string[]  $name  Name or names.
	 * @param   string|null      $as    Alias.
	 *
	 * @return  string|string[]
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null)
	{
		if (\is_array($name))
		{
			$quoted = [];

			foreach ($name as $part)
			{
				$quoted[] = (string) $part;
			}

			return $quoted;
		}

		if ($as !== null && $as !== '')
		{
			return $name . ' AS ' . $as;
		}

		return (string) $name;
	}

	/**
	 * @param   object   $query   Query.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0): self
	{
		$this->pending = $query instanceof MyactivitiesQueryStub ? $query : null;

		return $this;
	}

	/**
	 * @param   string|null  $key  Object list key.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null): array
	{
		$query = $this->pending;
		$this->pending = null;

		if ($query === null)
		{
			return [];
		}

		$sql = (string) $query;

		if (str_contains($sql, '#__wendfold_metric_definitions') && !str_contains($sql, '#__wendfold_metric_entries'))
		{
			$rows = $this->definitions;

			if ($key === null || $key === '')
			{
				return $rows;
			}

			$keyed = [];

			foreach ($rows as $row)
			{
				$keyed[(string) $row->{$key}] = $row;
			}

			return $keyed;
		}

		if (str_contains($sql, '#__wendfold_metric_entries'))
		{
			$profileId = (int) ($query->bound[':profileId'] ?? 0);
			$published = (int) ($query->bound[':published'] ?? 1);
			$start = (string) ($query->bound[':rangeStart'] ?? '1900-01-01');
			$end = (string) ($query->bound[':rangeEnd'] ?? '9999-12-31');
			$sums = [];
			$counts = [];
			$maxes = [];

			foreach ($this->entries as $entry)
			{
				if ((int) $entry['profile_id'] !== $profileId)
				{
					continue;
				}

				if ((int) $entry['published'] !== $published)
				{
					continue;
				}

				$day = (string) $entry['entry_date'];

				if ($day < $start || $day > $end)
				{
					continue;
				}

				$metricId = (int) $entry['metric_id'];
				$value = (float) $entry['value'];
				$sums[$metricId] = ($sums[$metricId] ?? 0.0) + $value;
				$counts[$metricId] = ($counts[$metricId] ?? 0) + 1;
				$maxes[$metricId] = max($maxes[$metricId] ?? $value, $value);
			}

			$rows = [];

			foreach ($sums as $metricId => $totalSum)
			{
				$row = (object) [
					'metric_id'   => $metricId,
					'total_sum'   => (string) $totalSum,
					'total_count' => (string) $counts[$metricId],
					'total_max'   => (string) $maxes[$metricId],
				];
				$rows[(string) $metricId] = $row;
			}

			if ($key === null || $key === '')
			{
				return array_values($rows);
			}

			return $rows;
		}

		return [];
	}

	/**
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		$query = $this->pending;
		$this->pending = null;

		if ($query === null)
		{
			return null;
		}

		$userId = (int) ($query->bound[':userId'] ?? 0);

		return $this->profilesByUserId[$userId] ?? null;
	}

	/**
	 * @return  null
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		return null;
	}
}

/**
 * Query stub for myactivities list and MetricQuery aggregates.
 *
 * @since  1.0.0
 */
final class MyactivitiesQueryStub
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
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $group = [];

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
	 * @param   mixed  $columns  Group columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function group($columns): self
	{
		foreach ((array) $columns as $column)
		{
			$this->group[] = (string) $column;
		}

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
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public function whereClauses(): array
	{
		return $this->where;
	}

	/**
	 * @param   integer  $limit   Limit.
	 * @param   integer  $offset  Offset.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setLimit($limit, $offset = 0): self
	{
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
			. ($this->group === [] ? '' : ' GROUP BY ' . implode(', ', $this->group))
			. ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order));
	}
}

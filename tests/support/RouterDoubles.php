<?php

/**
 * Wendfold - doubles for the site router tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Tests\Support;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;

/**
 * Application double. SEF parsing does not put Itemid on the request input.
 *
 * @since  1.0.0
 */
final class SiteApplication implements CMSApplicationInterface
{
	/**
	 * Request input.
	 *
	 * @var    RequestInput
	 * @since  1.0.0
	 */
	public $input;

	/**
	 * Create the application and its input.
	 *
	 * @since  1.0.0
	 */

	/**
	 * Current visitor. A guest when the test does not set one.
	 *
	 * @var    object
	 * @since  1.0.0
	 */
	private $user;

	/**
	 * Create the application, its input, and the visitor.
	 *
	 * @param   object|null  $user  Visitor, or null for a guest.
	 *
	 * @since   1.0.0
	 */
	public function __construct(?object $user = null)
	{
		$this->input = new RequestInput;
		$this->user = $user ?? new GuestUser;
	}

	/**
	 * Load the current visitor.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function getIdentity()
	{
		return $this->user;
	}
}

/**
 * Guest visitor. No profile and no component permissions.
 *
 * @since  1.0.0
 */
final class GuestUser
{
	/**
	 * Guest user id.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public $id = 0;

	/**
	 * Guests are denied every component action.
	 *
	 * @param   string  $action  Action name.
	 * @param   string  $asset   Asset name. Unused.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function authorise($action, $asset = null)
	{
		return false;
	}
}

/**
 * Signed-in visitor for a router test.
 *
 * @since  1.0.0
 */
final class MemberUser
{
	/**
	 * User id.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public $id;

	/**
	 * Whether authorise() allows component administration.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private $admin;

	/**
	 * Store the user id and the admin flag.
	 *
	 * @param   integer  $id     User id.
	 * @param   boolean  $admin  True when the user may manage the component.
	 *
	 * @since   1.0.0
	 */
	public function __construct(int $id, bool $admin = false)
	{
		$this->id = $id;
		$this->admin = $admin;
	}

	/**
	 * Allow component administration when this user was constructed as an admin.
	 *
	 * @param   string  $action  Action name.
	 * @param   string  $asset   Asset name. Unused.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function authorise($action, $asset = null)
	{
		if (!$this->admin)
		{
			return false;
		}

		return $action === 'core.admin' || $action === 'core.manage';
	}
}

/**
 * Request input that never carries an Itemid.
 *
 * Joomla's site router sets the active menu before the component parser
 * runs, and copies Itemid onto the input only after parsing returns.
 *
 * @since  1.0.0
 */
final class RequestInput
{
	/**
	 * Read an integer request value.
	 *
	 * Itemid is absent during SEF parsing, so the default is returned.
	 *
	 * @param   string   $name     Field name.
	 * @param   integer  $default  Value when the field is absent.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getInt($name, $default = 0)
	{
		if ($name === 'Itemid')
		{
			return $default;
		}

		return $default;
	}
}

/**
 * Menu double holding the Wendfold menu items for one test.
 *
 * @since  1.0.0
 */
final class SiteMenu extends AbstractMenu
{
	/**
	 * Menu items keyed by id.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $items;

	/**
	 * Menu item SiteRouter would have marked active.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	private $active;

	/**
	 * Store the menu items.
	 *
	 * @param   array  $items  Menu items keyed by id.
	 *
	 * @since   1.0.0
	 */
	public function __construct(array $items)
	{
		$this->items = $items;
	}

	/**
	 * Mark the item the site router activated before parse().
	 *
	 * @param   object|null  $item  Active menu item, or null when none matched.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setActiveItem($item): void
	{
		$this->active = $item;
	}

	/**
	 * Load one menu item.
	 *
	 * @param   integer  $id  Menu item id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getItem($id)
	{
		$id = (int) $id;

		if (isset($this->items[$id]))
		{
			return $this->items[$id];
		}

		return null;
	}

	/**
	 * Load every item. The tests only store Wendfold items.
	 *
	 * @param   string|array  $attributes  Unused attribute name or names.
	 * @param   mixed         $values      Unused attribute value or values.
	 * @param   boolean       $firstonly   Unused first-match flag.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getItems($attributes, $values, $firstonly = false)
	{
		return array_values($this->items);
	}

	/**
	 * Load the item the site router activated, if any.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getActive()
	{
		return $this->active;
	}
}

/**
 * Query double. The router chains select() and from() and then ignores it.
 *
 * @since  1.0.0
 */
final class QueryStub
{
	/**
	 * Accept the select list.
	 *
	 * @param   array  $columns  Column list.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		return $this;
	}

	/**
	 * Table the query reads.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $from = '';

	/**
	 * Accept the table name.
	 *
	 * @param   string  $table  Table name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function from($table)
	{
		$this->from = (string) $table;

		return $this;
	}

	/**
	 * Accept a WHERE clause. The router filters rows after they are loaded.
	 *
	 * @param   mixed   $condition  Condition.
	 * @param   string  $glue       Unused glue.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function where($condition, $glue = 'AND')
	{
		return $this;
	}

	/**
	 * Accept a JOIN clause.
	 *
	 * @param   string  $type       Join type.
	 * @param   string  $table      Table or ON clause.
	 * @param   string  $condition  Unused condition.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function join($type, $table, $condition = null)
	{
		return $this;
	}

	/**
	 * Accept an ORDER BY clause. Rows are sorted by the router.
	 *
	 * @param   string  $columns  Order expression.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function order($columns)
	{
		return $this;
	}
}

/**
 * Database double that returns the event rows prepared by the test.
 *
 * A profile query returns the profile rows. Every other query returns the
 * event rows. The router applies alias and visibility checks itself.
 *
 * @since  1.0.0
 */
final class DatabaseStub implements DatabaseInterface
{
	/**
	 * Event rows.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $rows;

	/**
	 * Profile rows.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $profiles;

	/**
	 * Table named by the query passed to setQuery().
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $from = '';

	/**
	 * Store the event rows and any profile rows.
	 *
	 * @param   array  $rows      Event rows.
	 * @param   array  $profiles  Profile rows for the visitor lookup.
	 *
	 * @since   1.0.0
	 */
	public function __construct(array $rows, array $profiles = [])
	{
		$this->rows = $rows;
		$this->profiles = $profiles;
	}

	/**
	 * Start a query.
	 *
	 * @param   boolean  $new  Unused.
	 *
	 * @return  QueryStub
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		return new QueryStub;
	}

	/**
	 * Create a new query object.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function createQuery()
	{
		return $this->getQuery(true);
	}

	/**
	 * Return the column name unchanged.
	 *
	 * @param   string  $name  Column name.
	 * @param   string  $as    Unused alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null)
	{
		return $name;
	}

	/**
	 * Accept the query. Rows are already stored on this double.
	 *
	 * @param   object   $query   Unused query.
	 * @param   integer  $offset  Unused row offset.
	 * @param   integer  $limit   Unused row limit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		$this->from = '';

		if (is_object($query) && isset($query->from))
		{
			$this->from = (string) $query->from;
		}
	}

	/**
	 * Return the prepared rows for the query table.
	 *
	 * @param   string  $key  Unused row key.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null)
	{
		if (str_contains($this->from, 'wendfold_profiles'))
		{
			return $this->profiles;
		}

		return $this->rows;
	}
}

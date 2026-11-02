<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Wendfold\Component\Wendfold\Administrator\Helper\EventAlias;

/**
 * Event table.
 *
 * check() stores the alias. The only insert path is administrator
 * EventModel::save(), including save and save as copy, which calls this
 * table. Other event writes update status or completion columns and do not
 * insert a row.
 *
 * @since  1.0.0
 */
class EventTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $_supportNullValue = true; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.ClassVarHasUnderscore

	/**
	 * Constructor.
	 *
	 * @param   DatabaseDriver  $db  Database driver.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__wendfold_events', 'id', $db);
		$this->setColumnAlias('published', 'published');
	}

	/**
	 * Validate the row and store a unique alias.
	 *
	 * A blank alias is generated from the title. A typed alias is normalized
	 * the same way and refused when another row already has it. Save as copy
	 * may take a numeric suffix. The lookup includes every row except this
	 * one, so an unpublished or trashed event still reserves its alias.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		if (empty($this->title))
		{
			$this->setError('Event title is required.');

			return false;
		}

		$task = (string) Factory::getApplication()->getInput()->get('task', '');
		$saveAsCopy = $task === 'save2copy' || str_ends_with($task, '.save2copy');

		// Factory::getDate() is UTC. Site-local time waits on the site-time helper.
		$alias = EventAlias::build(
			(string) ($this->alias ?? ''),
			(string) $this->title,
			(int) $this->id,
			$this->takenAliases((int) $this->id),
			Factory::getDate()->format('Y-m-d-H-i-s'),
			static fn (string $value): string => ApplicationHelper::stringURLSafe($value),
			$saveAsCopy
		);

		if ($alias === null)
		{
			$key = EventAlias::error() === EventAlias::ERROR_LENGTH
				? 'COM_WENDFOLD_ERROR_ALIAS_LENGTH'
				: 'COM_WENDFOLD_ERROR_UNIQUE_ALIAS';
			$this->setError(Text::_($key));

			return false;
		}

		$this->alias = $alias;

		return parent::check();
	}

	/**
	 * Stamp the author columns, then store the row.
	 *
	 * @param   boolean  $updateNulls  True to update null values.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function store($updateNulls = true): bool
	{
		$user = Factory::getApplication()->getIdentity();
		$date = Factory::getDate()->toSql();

		if (!$this->id)
		{
			$this->created    = $date;
			$this->created_by = $user->id;
		}

		$this->modified    = $date;
		$this->modified_by = $user->id;

		// The unique token is NULL until generated. An empty string would
		// collide once the column is UNIQUE.
		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name
		if (isset($this->qr_token) && trim((string) $this->qr_token) === '')
		{
			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name
			$this->qr_token = null;
		}

		return parent::store($updateNulls);
	}

	/**
	 * Aliases stored on every event except the row being saved.
	 *
	 * There is no published filter. Unpublished, archived, and trashed rows
	 * are included.
	 *
	 * @param   integer  $excludeId  Row to leave out. 0 loads every alias.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	private function takenAliases(int $excludeId): array
	{
		$db = $this->getDbo();
		$query = $db->createQuery()
			->select($db->quoteName('alias'))
			->from($db->quoteName('#__wendfold_events'));

		if ($excludeId > 0)
		{
			$query->where($db->quoteName('id') . ' <> ' . $excludeId);
		}

		$db->setQuery($query);
		$column = $db->loadColumn();

		if (!\is_array($column))
		{
			return [];
		}

		$aliases = [];

		foreach ($column as $alias)
		{
			$aliases[] = (string) $alias;
		}

		return $aliases;
	}
}

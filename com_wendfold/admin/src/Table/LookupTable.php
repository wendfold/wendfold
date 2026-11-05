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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupDeleteGuard;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupRecordRules;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;

/**
 * Shared checks for a chapter-editable lookup.
 *
 * @since  1.0.0
 */
abstract class LookupTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $_supportNullValue = true; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.ClassVarHasUnderscore

	/**
	 * Table whose rows reference this lookup, including the #__ prefix.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected string $usageTable = '';

	/**
	 * Column on the usage table that stores this lookup id.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected string $usageColumn = '';

	/**
	 * @param   DatabaseDriver  $db  Database connector.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct($this->lookupTable(), 'id', $db);
		$this->setColumnAlias('published', 'published');
	}

	/**
	 * Physical table name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	abstract protected function lookupTable(): string;

	/**
	 * Title is required. Subclasses add their own rules and then call this.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		$title = LookupRecordRules::title((string) $this->title);

		if (!$title['ok'])
		{
			$this->setError(Text::_($title['message']));

			return false;
		}

		$this->title = $title['title'];

		if ((int) $this->id < 1 && (int) $this->ordering < 1)
		{
			$this->ordering = $this->nextOrdering();
		}

		return parent::check();
	}

	/**
	 * Next ordering value for a new lookup row.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function nextOrdering(): int
	{
		$db = $this->getDbo();
		$query = $db->createQuery()
			->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
			->from($db->quoteName($this->lookupTable()));

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Refuse the delete when another row still points here.
	 *
	 * @param   integer|null  $pk  Primary key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function delete($pk = null): bool
	{
		$pk = (int) ($pk ?: $this->id);

		if ($pk > 0 && !$this->load($pk))
		{
			return false;
		}

		$count = $this->countUsage((int) $this->id);
		$message = LookupDeleteGuard::messageKey($count);

		if ($message !== null)
		{
			$this->setError(Text::sprintf($message, LookupTitle::display((string) $this->title), $count));

			return false;
		}

		return parent::delete($pk);
	}

	/**
	 * How many rows reference this lookup id.
	 *
	 * @param   integer  $id  Lookup id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function countUsage(int $id): int
	{
		if ($id < 1 || $this->usageTable === '' || $this->usageColumn === '')
		{
			return 0;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName($this->usageTable))
			->where($db->quoteName($this->usageColumn) . ' = :lookupId')
			->bind(':lookupId', $id, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Stamp the audit columns in UTC.
	 *
	 * @param   boolean  $updateNulls  True to write SQL NULL.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function store($updateNulls = true): bool
	{
		$date = Factory::getDate()->toSql();
		$user = Factory::getApplication()->getIdentity();
		$userId = (int) ($user->id ?? 0);

		$createdBy = 'created_by';
		$modifiedBy = 'modified_by';

		if (!(int) $this->id)
		{
			$this->created = $date;
			$this->$createdBy = $userId;
		}

		$this->modified = $date;
		$this->$modifiedBy = $userId;

		return parent::store($updateNulls);
	}
}

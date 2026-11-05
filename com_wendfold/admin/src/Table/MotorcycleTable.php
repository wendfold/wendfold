<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * admin/src/Table/MotorcycleTable.php
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class MotorcycleTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $_supportNullValue = true; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.ClassVarHasUnderscore

	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__wendfold_motorcycles', 'id', $db);
		$this->setColumnAlias('published', 'published');
	}

	public function check(): bool
	{
		if (empty($this->profile_id))
		{
			$this->setError('A member profile is required.');

			return false;
		}

		if (empty($this->make))
		{
			$this->make = '';
		}

		return parent::check();
	}

	/**
	 * Stamp the audit columns and keep model_year aligned with the legacy year column.
	 *
	 * @param   boolean  $updateNulls  True to update null fields.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function store($updateNulls = true): bool
	{
		$date = Factory::getDate()->toSql();
		$user = Factory::getApplication()->getIdentity();
		$createdBy = 'created_by';
		$modifiedBy = 'modified_by';
		$modelYear = 'model_year';

		if (empty($this->id))
		{
			if (empty($this->created))
			{
				$this->created = $date;
			}

			if (empty($this->$createdBy))
			{
				$this->$createdBy = (int) $user->id;
			}
		}

		$this->modified = $date;
		$this->$modifiedBy = (int) $user->id;

		if (isset($this->year) && $this->year !== '')
		{
			$this->$modelYear = (int) $this->year;
		}

		return parent::store($updateNulls);
	}
}

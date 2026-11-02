<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * Officer table class for managing chapter leadership appointments
 */

namespace Wendfold\Component\Wendfold\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Officer Table class
 *
 * @since  1.0.0
 */
class OfficerTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $_supportNullValue = true; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.ClassVarHasUnderscore

	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__wendfold_officers', 'id', $db);
	}

	/**
	 * Sanity checks before storing.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check()
	{
		// Required: member
		if (empty($this->profile_id))
		{
			$this->setError('A member must be selected.');

			return false;
		}

		$roleId = 'role_id';

		if (empty($this->$roleId))
		{
			$this->setError('An officer role must be selected.');

			return false;
		}

		// Default display order
		if ((int) $this->role_order === 0)
		{
			$this->role_order = 99;
		}

		// Convert empty date strings to null so MySQL DATE columns
		// don't receive '' which causes "Incorrect date value" errors
		if (empty($this->term_start))
		{
			$this->term_start = null;
		}

		if (empty($this->term_end))
		{
			$this->term_end = null;
		}

		// Validate term order only when both dates are present
		if ($this->term_start !== null && $this->term_end !== null)
		{
			if (strtotime($this->term_start) > strtotime($this->term_end))
			{
				$this->setError('Term end date cannot be before the term start date.');

				return false;
			}
		}

		// Auto-set is_current from dates when present
		$today = SiteTime::fromApplication()->today();

		if ($this->term_start !== null && $this->term_end !== null)
		{
			$this->is_current = ($this->term_start <= $today && $this->term_end >= $today) ? 1 : 0;
		}
		elseif ($this->term_start !== null)
		{
			// Start date set, no end date  -  assume currently serving
			$this->is_current = 1;
		}

		return parent::check();
	}

	/**
	 * Store the record, setting audit timestamps.
	 *
	 * @param   boolean  $updateNulls  Update null fields.
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

		if (empty($this->id))
		{
			$this->created = $date;
		}

		if (empty($this->$createdBy))
		{
			$this->$createdBy = (int) $user->id;
		}

		$this->modified = $date;
		$this->$modifiedBy = (int) $user->id;

		// The form still posts role_order. Keep the target ordering column in step.
		$legacyOrder = 'role_order';
		$this->ordering = (int) ($this->$legacyOrder ?? 0);

		return parent::store($updateNulls);
	}
}

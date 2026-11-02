<?php

/**
 * Wendfold - Chapter Management System
 * Member Table Class
 *
 * Maps the MemberModel to the #__wendfold_profiles database table.
 * Joomla's Table class handles all the low-level database operations:
 * load(), store(), delete(), check().
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;

/**
 * Wendfold Member Table
 *
 * This class lives at:
 *   admin/src/Table/MemberTable.php
 *
 * NAMING RULE:
 *   Class name : MemberTable
 *   File name  : MemberTable.php
 *   Directory  : admin/src/Table/
 *
 * The getTable() call in MemberModel uses name='Member' and
 * prefix='Table'  -  Joomla combines these to find MemberTable
 * in the Table directory automatically via PSR-4.
 */
class MemberTable extends Table
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
	 * @param   DatabaseDriver  $db  Database driver instance
	 */
	public function __construct(DatabaseDriver $db)
	{
		// Map this table class to the profiles database table
		// '#__wendfold_profiles' with 'id' as the primary key
		parent::__construct('#__wendfold_profiles', 'id', $db);

		// Set column aliases so Joomla's publishing system works
		// These map Joomla's expected column names to our actual ones
		$this->setColumnAlias('published', 'published');
	}

	/**
	 * Validates data before saving.
	 *
	 * Called automatically by store(). Return false to abort save.
	 * Add any business logic validation here.
	 *
	 * @return  bool
	 */
	public function check(): bool
	{
		// user_id is required - must link to a Joomla user
		if (empty($this->user_id))
		{
			$this->setError('A Joomla user account must be selected.');

			return false;
		}

		// Ensure we don't create duplicate profiles for the same user
		if (!$this->id)
		{
			$db    = $this->getDbo();
			$query = $db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_profiles'))
				->where($db->quoteName('user_id') . ' = ' . (int) $this->user_id);

			$count = (int) $db->setQuery($query)->loadResult();

			if ($count > 0)
			{
				$this->setError('This Joomla user already has a Wendfold member profile.');

				return false;
			}
		}

		return parent::check();
	}

	/**
	 * Actions to perform before storing the record.
	 *
	 * @param   bool  $updateNulls  Whether to update null fields
	 *
	 * @return  bool
	 */
	public function store($updateNulls = true): bool
	{
		$user = Factory::getApplication()->getIdentity();
		$date = Factory::getDate()->toSql();

		// ---------------------------------------------------------------
		// Defensive defaults
		//
		// We want an admin to be able to click Save even if optional fields
		// are untouched. The database schema uses many NOT NULL columns with
		// defaults (e.g. '', 0, 1). If Joomla's Table layer is given NULL for
		// those columns, MySQL strict mode can throw errors like:
		//   "Column 'photo' cannot be null".
		//
		// These assignments ensure we never write NULL into NOT NULL columns.
		// ---------------------------------------------------------------
		$memberNumber = trim((string) ($this->member_number ?? ''));
		$this->member_number = $memberNumber === '' ? null : $memberNumber;
		$this->chapter_on_file    = $this->chapter_on_file ?? '';
		$this->emergency_name     = $this->emergency_name ?? '';
		$this->emergency_phone    = $this->emergency_phone ?? '';
		$this->emergency_relation = $this->emergency_relation ?? '';
		$this->shirt_size         = $this->shirt_size ?? '';
		$this->photo              = $this->photo ?? '';

		// Integer-ish defaults
		$membershipTypeId = 'membership_type_id';

		if (isset($this->$membershipTypeId) && (int) $this->$membershipTypeId < 1)
		{
			$this->$membershipTypeId = null;
		}

		$this->published              = (int) ($this->published ?? 1);
		$this->status                 = (int) ($this->status ?? 1);
		$this->dues_paid              = (int) ($this->dues_paid ?? 0);
		$this->waiver_signed          = (int) ($this->waiver_signed ?? 0);
		$this->national_verified      = (int) ($this->national_verified ?? 0);
		$this->chapter_switch_flagged = (int) ($this->chapter_switch_flagged ?? 0);
		$this->completed_by           = (int) ($this->completed_by ?? 0);

		if (!(int) $this->id)
		{
			$waiverVersion = 'waiver_version';
			$configured = ComponentHelper::getParams('com_wendfold')->get('waiver_version', '');
			$this->$waiverVersion = ComponentOptions::waiverVersionForRecord(
				0,
				$this->$waiverVersion ?? '',
				$configured
			);

			// New record - set created metadata
			$this->created    = $date;
			$this->created_by = $user->id;
		}

		// Always update modified metadata
		$this->modified    = $date;
		$this->modified_by = $user->id;

		return parent::store($updateNulls);
	}
}

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

use Joomla\Database\DatabaseDriver;

/**
 * Membership type lookup.
 *
 * @since  1.0.0
 */
class MembershiptypeTable extends LookupTable
{
	/**
	 * @param   DatabaseDriver  $db  Database connector.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->usageTable = '#__wendfold_profiles';
		$this->usageColumn = 'membership_type_id';

		parent::__construct($db);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_membership_types';
	}

	/**
	 * Title is required. The import code is trimmed.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		$importCode = 'import_code';
		$exempt = 'exempt_from_renewal';
		$this->$importCode = trim((string) ($this->$importCode ?? ''));
		$this->$exempt = (int) ($this->$exempt ?? 0) === 1 ? 1 : 0;

		return parent::check();
	}
}

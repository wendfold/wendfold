<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

\defined('_JEXEC') or die;

/**
 * Membership type list.
 *
 * @since  1.0.0
 */
class MembershiptypesModel extends LookupListModel
{
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
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function extraColumns(): array
	{
		return ['a.import_code', 'a.exempt_from_renewal'];
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function orderColumns(): array
	{
		return ['a.ordering', 'a.title', 'a.import_code', 'a.published', 'a.id', 'a.exempt_from_renewal'];
	}
}

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
 * Officer role list.
 *
 * @since  1.0.0
 */
class OfficerrolesModel extends LookupListModel
{
	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_officer_roles';
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function extraColumns(): array
	{
		return ['a.system_key'];
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function orderColumns(): array
	{
		return ['a.ordering', 'a.title', 'a.system_key', 'a.published', 'a.id'];
	}
}

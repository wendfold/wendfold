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
 * Award definition list.
 *
 * @since  1.0.0
 */
class AwarddefinitionsModel extends LookupListModel
{
	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_award_definitions';
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function extraColumns(): array
	{
		return [
			'a.period_type',
			'a.match_mode',
			'a.repeat_mode',
			'a.revoke_policy',
		];
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function orderColumns(): array
	{
		return [
			'a.ordering',
			'a.title',
			'a.published',
			'a.id',
			'a.period_type',
			'a.match_mode',
			'a.repeat_mode',
			'a.revoke_policy',
		];
	}
}

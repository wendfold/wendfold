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
 * Metric definition list.
 *
 * @since  1.0.0
 */
class MetricdefinitionsModel extends LookupListModel
{
	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_metric_definitions';
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function extraColumns(): array
	{
		return [
			'a.metric_key',
			'a.unit_label',
			'a.aggregation',
			'a.allow_event_credit',
			'a.allow_member_entry',
			'a.show_on_dashboard',
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
			'a.metric_key',
			'a.unit_label',
			'a.published',
			'a.id',
			'a.aggregation',
			'a.allow_event_credit',
			'a.allow_member_entry',
			'a.show_on_dashboard',
		];
	}
}

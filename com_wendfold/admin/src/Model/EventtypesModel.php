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
 * Event type list.
 *
 * @since  1.0.0
 */
class EventtypesModel extends LookupListModel
{
	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_event_types';
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function extraColumns(): array
	{
		return ['a.behavior', 'a.default_riding', 'a.default_workflow_mode'];
	}

	/**
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function orderColumns(): array
	{
		return ['a.ordering', 'a.title', 'a.behavior', 'a.published', 'a.id'];
	}
}

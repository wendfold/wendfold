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

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupRecordRules;

/**
 * Event type lookup.
 *
 * @since  1.0.0
 */
class EventtypeTable extends LookupTable
{
	/**
	 * @param   DatabaseDriver  $db  Database connector.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->usageTable = '#__wendfold_events';
		$this->usageColumn = 'event_type_id';

		parent::__construct($db);
	}

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
	 * Title is required. Behavior is one of the four hard-coded values.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		$behavior = LookupRecordRules::behavior((int) ($this->behavior ?? 0));

		if (!$behavior['ok'])
		{
			$this->setError(Text::_($behavior['message']));

			return false;
		}

		$defaultRiding = 'default_riding';
		$defaultWorkflow = 'default_workflow_mode';
		$this->behavior = $behavior['behavior'];
		$this->$defaultRiding = (int) ($this->$defaultRiding ?? 0) === 1 ? 1 : 0;
		$this->$defaultWorkflow = LookupRecordRules::workflow((int) ($this->$defaultWorkflow ?? 1));

		return parent::check();
	}
}

<?php

/**
 * Wendfold - Event type lookup stand-in for save tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Wendfold\Component\Wendfold\Administrator\Model\EventModel;

/**
 * Resolves the old fixture type numbers to behavior and defaults.
 *
 * @since  1.0.0
 */
class FixtureEventModel extends EventModel
{
	/**
	 * @param   integer  $eventTypeId  Fixture id. 1 ride, 2 meeting, 6 overnight.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	protected function lookupEventType(int $eventTypeId): ?object
	{
		$map = [
			1 => [1, 2, 1],
			2 => [2, 3, 0],
			3 => [1, 2, 1],
			4 => [4, 1, 0],
			5 => [4, 1, 0],
			6 => [3, 5, 1],
		];

		if (!isset($map[$eventTypeId]))
		{
			return null;
		}

		[$behavior, $workflow, $riding] = $map[$eventTypeId];

		return (object) [
			'id'                     => $eventTypeId,
			'behavior'               => $behavior,
			'default_workflow_mode'  => $workflow,
			'default_riding'         => $riding,
			'published'              => 1,
		];
	}
}

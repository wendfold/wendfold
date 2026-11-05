<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Detects cycles among award prerequisite edges.
 *
 * @since  1.0.0
 */
final class AwardPrerequisiteGraph
{
	/**
	 * Whether adding or replacing edges for $awardId would create a cycle.
	 *
	 * $edges maps award_id => list of required_award_id values from type-3 criteria.
	 * The edges for $awardId are replaced with $requiredAwardIds before the DFS.
	 *
	 * @param   array<int, list<int>>  $edges             Existing graph.
	 * @param   integer                $awardId           Award being saved.
	 * @param   list<int>              $requiredAwardIds  Its type-3 prerequisites.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function wouldCycle(array $edges, int $awardId, array $requiredAwardIds): bool
	{
		if ($awardId < 1)
		{
			return false;
		}

		$clean = [];

		foreach ($requiredAwardIds as $required)
		{
			$required = (int) $required;

			if ($required < 1)
			{
				continue;
			}

			if ($required === $awardId)
			{
				return true;
			}

			$clean[$required] = $required;
		}

		$edges[$awardId] = array_values($clean);

		$visited = [];
		$stack = [];

		return self::dfs($awardId, $edges, $visited, $stack);
	}

	/**
	 * Depth-first search for a back edge.
	 *
	 * @param   integer                $node     Current award id.
	 * @param   array<int, list<int>>  $edges    Graph.
	 * @param   array<int, true>       $visited  Finished nodes.
	 * @param   array<int, true>       $stack    Active recursion stack.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function dfs(int $node, array $edges, array &$visited, array &$stack): bool
	{
		if (isset($stack[$node]))
		{
			return true;
		}

		if (isset($visited[$node]))
		{
			return false;
		}

		$stack[$node] = true;

		foreach ($edges[$node] ?? [] as $next)
		{
			$next = (int) $next;

			if ($next < 1)
			{
				continue;
			}

			if (self::dfs($next, $edges, $visited, $stack))
			{
				return true;
			}
		}

		unset($stack[$node]);
		$visited[$node] = true;

		return false;
	}
}

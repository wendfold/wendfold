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
 * Validation shared by the lookup tables.
 *
 * The table classes call these rules from check(). A failed rule carries the
 * language key the form shows.
 *
 * @since  1.0.0
 */
final class LookupRecordRules
{
	/**
	 * Trim a title and refuse a blank one.
	 *
	 * @param   string  $title  Posted title.
	 *
	 * @return  array{ok: bool, title: string, message: string}
	 *
	 * @since   1.0.0
	 */
	public static function title(string $title): array
	{
		$title = trim($title);

		if ($title === '')
		{
			return [
				'ok'      => false,
				'title'   => '',
				'message' => 'COM_WENDFOLD_LOOKUP_TITLE_REQUIRED',
			];
		}

		return [
			'ok'      => true,
			'title'   => $title,
			'message' => '',
		];
	}

	/**
	 * Validate an officer role system key.
	 *
	 * @param   string|null  $systemKey  Posted key.
	 *
	 * @return  array{ok: bool, systemKey: string|null, message: string}
	 *
	 * @since   1.0.0
	 */
	public static function systemKey(?string $systemKey): array
	{
		$normalised = OfficerRoleKey::normalise($systemKey);

		if ($normalised === false)
		{
			return [
				'ok'        => false,
				'systemKey' => null,
				'message'   => 'COM_WENDFOLD_OFFICER_ROLE_SYSTEM_KEY_INVALID',
			];
		}

		return [
			'ok'        => true,
			'systemKey' => $normalised,
			'message'   => '',
		];
	}

	/**
	 * Validate an event type behavior.
	 *
	 * @param   integer  $behavior  Posted behavior.
	 *
	 * @return  array{ok: bool, behavior: int, message: string}
	 *
	 * @since   1.0.0
	 */
	public static function behavior(int $behavior): array
	{
		if (!EventTypeBehavior::isValid($behavior))
		{
			return [
				'ok'       => false,
				'behavior' => EventTypeBehavior::General->value,
				'message'  => 'COM_WENDFOLD_EVENT_TYPE_BEHAVIOR_INVALID',
			];
		}

		return [
			'ok'       => true,
			'behavior' => $behavior,
			'message'  => '',
		];
	}

	/**
	 * Workflow values an event type may store as its default.
	 *
	 * @param   integer  $workflow  Posted workflow.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function workflow(int $workflow): int
	{
		if (\in_array($workflow, [1, 2, 3, 4, 5], true))
		{
			return $workflow;
		}

		return 1;
	}
}

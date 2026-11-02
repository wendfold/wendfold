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
 * Stable officer role keys.
 *
 * A chapter can rename the role title. Code that needs the head road captain
 * tests officer_roles.system_key, never the title.
 *
 * @since  1.0.0
 */
final class OfficerRoleKey
{
	/**
	 * The officer who leads road captains. Seeded on one role. Unique.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const HEAD_ROAD_CAPTAIN = 'head_road_captain';

	/**
	 * Officers who may manage event volunteers.
	 *
	 * This key is not seeded. The install rows are Director, Assistant
	 * Director, Secretary, Treasurer, Activities, Safety, Head Road Captain,
	 * Membership, Editor, Webmaster, Historian, and Photographer. The event
	 * screen used to match the title Volunteer Officer, and that title is
	 * not one of those rows. A chapter that wants the same check sets this
	 * key on the role it uses.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const VOLUNTEER_OFFICER = 'volunteer_officer';

	/**
	 * Whether this stored key is the head road captain.
	 *
	 * @param   string|null  $systemKey  officer_roles.system_key, or null.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isHeadRoadCaptain(?string $systemKey): bool
	{
		return $systemKey === self::HEAD_ROAD_CAPTAIN;
	}

	/**
	 * Normalise a posted system key.
	 *
	 * An empty string becomes null so several chapter-defined roles can omit
	 * the key. MySQL unique indexes allow multiple NULLs. An invalid key is
	 * false so the form can refuse it.
	 *
	 * @param   string|null  $systemKey  Raw posted value.
	 *
	 * @return  string|null|false  Key, null when empty, false when invalid.
	 *
	 * @since   1.0.0
	 */
	public static function normalise(?string $systemKey): string|null|false
	{
		$key = strtolower(trim((string) $systemKey));

		if ($key === '')
		{
			return null;
		}

		if (preg_match('/^[a-z][a-z0-9_]{0,49}$/', $key) !== 1)
		{
			return false;
		}

		return $key;
	}
}

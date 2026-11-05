<?php

/**
 * Wendfold - Site audience scope helper.
 *
 * Guests see scope 1. Members see 1 and 2. Road captains see 1, 2 and 4.
 * Officers see 1, 2, 3 and 4. Scope 4 is an officer or a road captain.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Decides which audience_scope values a visitor may see.
 *
 * Scope values: 1 All Visitors, 2 Logged-in Members, 3 Officers Only,
 * 4 Road Captains Only. Any other value is denied.
 *
 * Pass null for a guest or a signed-in account with no published member
 * profile. Pass is_officer and is_road_captain for a published profile.
 *
 * @since  1.0.0
 */
abstract class AudienceHelper
{
	/**
	 * All Visitors.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const SCOPE_ALL_VISITORS = 1;

	/**
	 * Signed-in user with a published member profile.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const SCOPE_MEMBERS = 2;

	/**
	 * Current published officer record.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const SCOPE_OFFICERS = 3;

	/**
	 * Road Captains Only. An officer or a road captain may see it.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const SCOPE_ROAD_CAPTAINS = 4;

	/**
	 * Whether this visitor may see an event with $scope.
	 *
	 * @param   int         $scope  Event audience_scope value.
	 * @param   array|null  $flags  Published-profile flags, or null when there is none.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function canAccess(int $scope, ?array $flags): bool
	{
		return \in_array($scope, self::allowedScopes($flags), true);
	}

	/**
	 * Whether this visitor may open the event and download its GPX file.
	 *
	 * Audience scope is the normal rule. A volunteer manager for the event,
	 * or a component administrator, may open an event outside that scope.
	 * Both the detail page and the GPX download use this decision.
	 *
	 * @param   integer     $scope           Event audience_scope value.
	 * @param   array|null  $flags           Published-profile flags, or null.
	 * @param   boolean     $isEventManager  True when the profile manages this event.
	 * @param   boolean     $isAdmin         True for core.admin or core.manage on the component.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function canSeeEvent(int $scope, ?array $flags, bool $isEventManager, bool $isAdmin): bool
	{
		if (self::canAccess($scope, $flags))
		{
			return true;
		}

		return $isEventManager || $isAdmin;
	}

	/**
	 * Audience values this visitor may see, in ascending order.
	 *
	 * Null (guest or no published profile) is scope 1 only. A published
	 * profile adds scope 2. A current officer adds scopes 3 and 4. A road
	 * captain adds scope 4. Unknown values are never included.
	 *
	 * @param   array|null  $flags  Keys is_officer and is_road_captain, or null.
	 *
	 * @return  int[]
	 *
	 * @since   1.0.0
	 */
	public static function allowedScopes(?array $flags): array
	{
		if ($flags === null)
		{
			return [self::SCOPE_ALL_VISITORS];
		}

		$scopes = [self::SCOPE_ALL_VISITORS, self::SCOPE_MEMBERS];

		if (!empty($flags['is_officer']))
		{
			$scopes[] = self::SCOPE_OFFICERS;
		}

		if (!empty($flags['is_officer']) || !empty($flags['is_road_captain']))
		{
			$scopes[] = self::SCOPE_ROAD_CAPTAINS;
		}

		return $scopes;
	}

	/**
	 * Map a published profile row to the flags array, or null when there is no row.
	 *
	 * @param   object|null  $profile  Row with is_officer and is_road_captain, or null.
	 *
	 * @return  array|null  Null when $profile is null.
	 *
	 * @since   1.0.0
	 */
	public static function flagsFromProfile(?object $profile): ?array
	{
		if ($profile === null)
		{
			return null;
		}

		$row = get_object_vars($profile);

		return [
			'is_officer'      => !empty($row['is_officer']),
			'is_road_captain' => !empty($row['is_road_captain']),
		];
	}
}

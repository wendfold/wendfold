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

use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;

/**
 * Meeting roll-call choices that follow the event's audience.
 *
 * Meeting kinds used to be a fixed subtype number. They are event types now.
 * Who is recommended, and who may take the roll, follows audience_scope.
 *
 * @since  1.0.0
 */
final class MeetingAudience
{
	/**
	 * Pool mode for a meeting audience.
	 *
	 * Officers and road captains narrow the recommended list. Every other
	 * audience uses the standard RSVP pool.
	 *
	 * @param   integer  $audienceScope  Event audience_scope.
	 *
	 * @return  string  officers, road_captains, or standard.
	 *
	 * @since   1.0.0
	 */
	public static function poolMode(int $audienceScope): string
	{
		return match ($audienceScope) {
			AudienceHelper::SCOPE_OFFICERS      => 'officers',
			AudienceHelper::SCOPE_ROAD_CAPTAINS => 'road_captains',
			default                             => 'standard',
		};
	}

	/**
	 * Whether this member may take the roll for that audience.
	 *
	 * Road-captain meetings allow an officer or a road captain. Every other
	 * meeting allows an officer. The caller has already checked that the
	 * event is a meeting and that the member can see the audience.
	 *
	 * @param   integer  $audienceScope   Event audience_scope.
	 * @param   boolean  $isOfficer       Current officer.
	 * @param   boolean  $isRoadCaptain   Road captain flag.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function canTakeRoll(int $audienceScope, bool $isOfficer, bool $isRoadCaptain): bool
	{
		if ($audienceScope === AudienceHelper::SCOPE_ROAD_CAPTAINS)
		{
			return $isOfficer || $isRoadCaptain;
		}

		return $isOfficer;
	}
}

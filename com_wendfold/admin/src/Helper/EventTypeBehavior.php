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
 * Hard-coded event type behaviors.
 *
 * Chapters name event types themselves. Code branches only on these four
 * values, which are stored on #__wendfold_event_types.behavior.
 *
 * @since  1.0.0
 */
enum EventTypeBehavior: int
{
	/**
	 * A ride. Ride details and the ride workflow apply.
	 *
	 * @since  1.0.0
	 */
	case Ride = 1;

	/**
	 * A meeting. Meeting roll call applies. Former meeting subtypes are types.
	 *
	 * @since  1.0.0
	 */
	case Meeting = 2;

	/**
	 * An overnight trip. Trip fields apply.
	 *
	 * @since  1.0.0
	 */
	case Overnight = 3;

	/**
	 * Anything that is not a ride, a meeting, or an overnight trip.
	 *
	 * @since  1.0.0
	 */
	case General = 4;

	/**
	 * Whether the stored integer is one of the four behaviors.
	 *
	 * @param   integer  $value  Value from event_types.behavior.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isValid(int $value): bool
	{
		return self::tryFrom($value) instanceof self;
	}

	/**
	 * Workflow a new event uses when the type did not store one.
	 *
	 * Ride is 2, meeting is 3, overnight is 5, and general is 1.
	 *
	 * @param   integer  $behavior  Event type behavior.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function workflow(int $behavior): int
	{
		return match (self::tryFrom($behavior)) {
			self::Ride      => 2,
			self::Meeting   => 3,
			self::Overnight => 5,
			default         => 1,
		};
	}

	/**
	 * Whether ride details should start on for this behavior.
	 *
	 * @param   integer  $behavior  Event type behavior.
	 *
	 * @return  integer  1 or 0.
	 *
	 * @since   1.0.0
	 */
	public static function defaultRiding(int $behavior): int
	{
		return match (self::tryFrom($behavior)) {
			self::Ride, self::Overnight => 1,
			default => 0,
		};
	}

	/**
	 * A ride, from the type behavior alone.
	 *
	 * @param   integer  $behavior  Event type behavior.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isRide(int $behavior): bool
	{
		return self::tryFrom($behavior) === self::Ride;
	}

	/**
	 * A meeting, from the type behavior or a stored meeting workflow.
	 *
	 * @param   integer  $behavior      Event type behavior.
	 * @param   integer  $workflowMode  Stored workflow. 3 is meeting roll call.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isMeeting(int $behavior, int $workflowMode = 0): bool
	{
		return self::tryFrom($behavior) === self::Meeting || $workflowMode === 3;
	}

	/**
	 * An overnight trip, from the type behavior or a stored overnight workflow.
	 *
	 * @param   integer  $behavior      Event type behavior.
	 * @param   integer  $workflowMode  Stored workflow. 5 is overnight.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isOvernight(int $behavior, int $workflowMode = 0): bool
	{
		return self::tryFrom($behavior) === self::Overnight || $workflowMode === 5;
	}

	/**
	 * Front-end badge class for a behavior.
	 *
	 * @param   integer  $behavior  Event type behavior.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function siteBadgeClass(int $behavior): string
	{
		return match (self::tryFrom($behavior)) {
			self::Ride    => 'hc-badge-ride',
			self::Meeting => 'hc-badge-meeting',
			default       => 'hc-badge-neutral',
		};
	}

	/**
	 * Administrator badge class for a behavior.
	 *
	 * @param   integer  $behavior  Event type behavior.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function adminBadgeClass(int $behavior): string
	{
		return match (self::tryFrom($behavior)) {
			self::Ride      => 'bg-primary',
			self::Meeting   => 'bg-info text-dark',
			self::Overnight => 'bg-dark',
			default         => 'bg-secondary',
		};
	}
}

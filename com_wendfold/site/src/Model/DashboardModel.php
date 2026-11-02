<?php
/**
 * Wendfold - Chapter Management System
 * Site Member Dashboard Model
 *
 * Aggregates data for the logged-in member's personal dashboard:
 *   - getProfile()        Full profile + Joomla user row
 *   - getStats()          YTD miles, YTD events attended, dues status
 *   - getUpcomingRsvps()  Upcoming events tied to the member
 *   - getPastRsvps()      Past events tied to the member (last 10)
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

// Database column names are snake_case on metric definition rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricQuery;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Dashboard Model
 *
 * All methods are scoped to the currently authenticated Joomla user.
 * Dues renewal is derived from the dues_payments.year column (YEAR type);
 * there is no renewal_date column - renewal = Dec 31 of the paid year.
 *
 * Schema gotchas:
 *   - events table uses event_city / event_state (NOT ksu_city / ksu_state)
 *   - rsvps table has no rsvp_date column - use r.created aliased as rsvp_date
 *   - attendance uses check_in_time (not checkin_time)
 *
 * @since  1.0.0
 */
class DashboardModel extends BaseDatabaseModel
{
	/**
	 * Returns the member's profile row joined with Joomla user data.
	 *
	 * Returns null for guests or users without a Wendfold profile.
	 *
	 * @return  object|null  Profile object with Joomla user name/email, or null.
	 *
	 * @since   1.0.0
	 */
	public function getProfile(): ?object
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user || $user->guest)
		{
			return null;
		}

		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select('p.*, u.name, u.email')
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->where($db->quoteName('p.user_id') . ' = ' . (int) $user->id);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Returns YTD statistics for the logged-in member.
	 *
	 * Dues status is derived from the dues_payments table (keyed by YEAR),
	 * not from a renewal_date column. Renewal = Dec 31 of the paid year.
	 *
	 * Status values: 'current', 'expiring' (within 60 days), 'expired', 'none', 'unknown'.
	 *
	 * @return  object  Stats with ytd_miles (int), ytd_events (int),
	 *                  dues_status (string), renewal_date (string|null).
	 *
	 * @since   1.0.0
	 */
	public function getStats(): object
	{
		$profile = $this->getProfile();

		if (!$profile)
		{
			return (object) [
				'year'         => (int) MetricPeriod::calendarYear(MetricPeriod::siteZone())['year'],
				'metrics'      => [],
				'dues_status'  => 'unknown',
				'renewal_date' => null,
			];
		}

		$db = $this->getDatabase();
		$period = MetricPeriod::calendarYear(MetricPeriod::siteZone());
		$aggregate = MetricQuery::profileAggregate($db, (int) $profile->id, $period['start'], $period['end']);
		$metrics = [];

		foreach (MetricQuery::dashboardDefinitions($db) as $definition)
		{
			$row = $aggregate[(int) $definition->id] ?? null;
			$metrics[] = (object) [
				'key' => (string) $definition->metric_key,
				'label' => LookupTitle::display((string) $definition->title),
				'unit' => (string) $definition->unit_label,
				'decimals' => (int) $definition->decimals,
				'total' => $row === null ? '0' : MetricQuery::pickedTotal($row, (int) $definition->aggregation),
			];
		}

		// Dues: find most recent payment year
		$duesQuery = $db->createQuery()
			->select('MAX(' . $db->quoteName('year') . ')')
			->from($db->quoteName('#__wendfold_dues_payments'))
			->where($db->quoteName('profile_id') . ' = ' . (int) $profile->id);

		$paidYear = (int) $db->setQuery($duesQuery)->loadResult();

		$duesStatus  = 'none';
		$renewalDate = null;

		if ($paidYear > 0)
		{
			// Renewal = Dec 31 of the paid year
			$renewalDate = $paidYear . '-12-31';
			$duesStatus  = SiteTime::fromApplication()->duesStatus($renewalDate);
		}

		return (object) [
			'year'         => $period['year'],
			'metrics'      => $metrics,
			'dues_status'  => $duesStatus,
			'renewal_date' => $renewalDate,
		];
	}

	/**
	 * Returns upcoming events (on or after today) tied to the member.
	 *
	 * This includes explicit RSVP rows plus events where the member is the
	 * assigned Road Captain, Sweep, Trip Lead, or Trip Sweep. Ordered by event_date ascending.
	 * Excludes cancelled events (status 3). Returns only published events.
	 *
	 * Columns returned: event_id, title, event_date, ksu_time, event_city,
	 *                   event_state, event_type, rsvp_date.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getUpcomingRsvps(): array
	{
		$profile = $this->getProfile();

		if (!$profile)
		{
			return [];
		}

		$db        = $this->getDatabase();
		$siteTime  = SiteTime::fromApplication();
		$today     = $siteTime->today();
		$profileId = (int) $profile->id;

		$query = $db->createQuery()
			->select([
				'DISTINCT ' . $db->quoteName('e.id') . ' AS event_id',
				$db->quoteName('e.title'),
				$db->quoteName('e.event_date'),
				$db->quoteName('e.ksu_time'),
				$db->quoteName('e.event_city'),
				$db->quoteName('e.event_state'),
				$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'),
				$db->quoteName('e.workflow_mode'),
				$db->quoteName('e.parent_event_id'),
				$db->quoteName('r.created', 'rsvp_date'),
				'CASE WHEN ' . $db->quoteName('e.road_captain_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_road_captain',
				'CASE WHEN ' . $db->quoteName('e.sweep_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_sweep',
				'CASE WHEN ' . $db->quoteName('e.trip_lead_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_trip_lead',
				'CASE WHEN ' . $db->quoteName('e.trip_sweep_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_trip_sweep',
				]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->leftJoin(
				$db->quoteName('#__wendfold_rsvps', 'r')
				. ' ON ' . $db->quoteName('r.event_id') . ' = ' . $db->quoteName('e.id')
				. ' AND ' . $db->quoteName('r.profile_id') . ' = ' . $profileId
			)
			->where(
				'(' . $db->quoteName('r.id') . ' IS NOT NULL'
				. ' OR ' . $db->quoteName('e.road_captain_id') . ' = ' . $profileId
				. ' OR ' . $db->quoteName('e.sweep_id') . ' = ' . $profileId
				. ' OR ' . $db->quoteName('e.trip_lead_id') . ' = ' . $profileId
				. ' OR ' . $db->quoteName('e.trip_sweep_id') . ' = ' . $profileId
				. ')'
			)
			->where($db->quoteName('e.event_date') . ' ' . $siteTime->eventsListOperator('upcoming') . ' ' . $db->quote($today))
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.status') . ' != 3')
			->where($db->quoteName('e.audience_scope') . ' IN (' . $this->allowedAudienceList() . ')')
			->order($db->quoteName('e.event_date') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Returns past events (before today) tied to the member.
	 *
	 * This includes explicit RSVP rows plus events where the member was the
	 * assigned Road Captain or Sweep. Ordered by event_date descending.
	 * Capped at $limit rows to keep the dashboard lightweight.
	 *
	 * Columns returned: event_id, title, event_date, event_city, event_state,
	 *                   event_type, rsvp_date.
	 *
	 * @param   int  $limit  Maximum rows to return (default 10).
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getPastRsvps(int $limit = 10): array
	{
		$profile = $this->getProfile();

		if (!$profile)
		{
			return [];
		}

		$db        = $this->getDatabase();
		$siteTime  = SiteTime::fromApplication();
		$today     = $siteTime->today();
		$profileId = (int) $profile->id;

		$query = $db->createQuery()
			->select([
				'DISTINCT ' . $db->quoteName('e.id') . ' AS event_id',
				$db->quoteName('e.title'),
				$db->quoteName('e.event_date'),
				$db->quoteName('e.event_city'),
				$db->quoteName('e.event_state'),
				$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'),
				$db->quoteName('e.workflow_mode'),
				$db->quoteName('e.parent_event_id'),
				$db->quoteName('r.created', 'rsvp_date'),
				'CASE WHEN ' . $db->quoteName('e.road_captain_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_road_captain',
				'CASE WHEN ' . $db->quoteName('e.sweep_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_sweep',
				'CASE WHEN ' . $db->quoteName('e.trip_lead_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_trip_lead',
				'CASE WHEN ' . $db->quoteName('e.trip_sweep_id') . ' = ' . $profileId . ' THEN 1 ELSE 0 END AS is_trip_sweep',
				]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->leftJoin(
				$db->quoteName('#__wendfold_rsvps', 'r')
				. ' ON ' . $db->quoteName('r.event_id') . ' = ' . $db->quoteName('e.id')
				. ' AND ' . $db->quoteName('r.profile_id') . ' = ' . $profileId
			)
			->where(
				'(' . $db->quoteName('r.id') . ' IS NOT NULL'
				. ' OR ' . $db->quoteName('e.road_captain_id') . ' = ' . $profileId
				. ' OR ' . $db->quoteName('e.sweep_id') . ' = ' . $profileId
				. ' OR ' . $db->quoteName('e.trip_lead_id') . ' = ' . $profileId
				. ' OR ' . $db->quoteName('e.trip_sweep_id') . ' = ' . $profileId
				. ')'
			)
			->where($db->quoteName('e.event_date') . ' ' . $siteTime->eventsListOperator('past') . ' ' . $db->quote($today))
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.audience_scope') . ' IN (' . $this->allowedAudienceList() . ')')
			->order($db->quoteName('e.event_date') . ' DESC');

		$query->setLimit($limit, 0);

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Comma-separated audience values the current user may see.
	 *
	 * A guest or an account without a published profile is scope 1 only.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function allowedAudienceList(): string
	{
		$user = Factory::getApplication()->getIdentity();
		$userId = (int) ($user->id ?? 0);

		if ($userId <= 0)
		{
			return implode(',', AudienceHelper::allowedScopes(null));
		}

		$db = $this->getDatabase();
		$limitQuery = $db->createQuery()
			->select(
				[
						'p.is_road_captain',
						'CASE WHEN o.id IS NULL THEN 0 ELSE 1 END AS is_officer',
					]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin(
				$db->quoteName('#__wendfold_officers', 'o')
					. ' ON o.profile_id = p.id AND o.is_current = 1 AND o.published = 1'
			)
			->where($db->quoteName('p.user_id') . ' = ' . $userId)
			->where($db->quoteName('p.published') . ' = 1');
		$limitQuery->setLimit(1, 0);
		$row = $db->setQuery($limitQuery)
			->loadObject();

		return implode(',', AudienceHelper::allowedScopes(AudienceHelper::flagsFromProfile($row ?: null)));
	}
}

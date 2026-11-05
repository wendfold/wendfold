<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$upcoming = $this->upcoming;
$past = $this->past;
$dashUrl = RouteHelper::dashboard();
$eventsUrl = RouteHelper::events();
$cancelUrl = RouteHelper::task('myrsvps.cancel');
$routeToEventDetail = static function ($eventLike): string {
	return RouteHelper::detail($eventLike);
};

$volunteerStatusLabels = [
	1 => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_VOLUNTEERED'),
	2 => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED'),
	3 => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_WAITLISTED'),
];

$renderVolunteerMeta = static function ($rsvp) use ($volunteerStatusLabels): string {
	if (empty($rsvp->volunteer_role_title)) {
		return '';
	}

	$statusLabel = $volunteerStatusLabels[(int) ($rsvp->volunteer_assignment_status ?? 0)] ?? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED');

	return Text::sprintf('COM_WENDFOLD_MYRSVPS_VOLUNTEER_META', (string) $rsvp->volunteer_role_title, $statusLabel);
};

$renderGroupMeta = static function ($rsvp): string {
	if (empty($rsvp->group_title)) {
		return '';
	}

	$parts = [Text::_('COM_WENDFOLD_EVENT_GROUP_LABEL') . ': ' . (string) $rsvp->group_title];

	if (!empty($rsvp->is_group_road_captain)) {
		$parts[] = Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_RC');
	}

	if (!empty($rsvp->is_group_sweep)) {
		$parts[] = Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_SWEEP');
	}

	if (!empty($rsvp->group_road_captain_name)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN') . ': ' . (string) $rsvp->group_road_captain_name;
	}

	if (!empty($rsvp->group_sweep_name)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_SWEEP') . ': ' . (string) $rsvp->group_sweep_name;
	}

	if (!empty($rsvp->group_staging_hint)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_GROUP_STAGING_HINT') . ': ' . (string) $rsvp->group_staging_hint;
	}

	if (!empty($rsvp->group_order_hint)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_GROUP_ORDER_HINT') . ': ' . (string) $rsvp->group_order_hint;
	}

	return implode(' - ', $parts);
};

$renderAttendanceMeta = static function ($rsvp): string {
	if (empty($rsvp->has_attendance)) {
		return '';
	}

	if (!empty($rsvp->attendance_time)) {
		return Text::sprintf(
			'COM_WENDFOLD_MYRSVPS_ATTENDANCE_META_TIME',
			HTMLHelper::_('date', (string) $rsvp->attendance_time, 'D, M j, Y g:i A')
		);
	}

	return Text::_('COM_WENDFOLD_MYRSVPS_ATTENDANCE_META');
};


$tripStatusLabels = [
	0 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOT_SET'),
	1 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_INTERESTED'),
	2 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_BOOKING'),
	3 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_BOOKED'),
	4 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_OWN'),
	5 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOT_NEEDED'),
];

$renderTripMeta = static function ($rsvp) use ($tripStatusLabels): string {
	$isOvernight = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($rsvp->event_behavior ?? 0), (int) ($rsvp->workflow_mode ?? 0)); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	if (!$isOvernight) {
		return '';
	}

	$parts = [];
	$statusLabel = $tripStatusLabels[(int) ($rsvp->lodging_status ?? 0)] ?? $tripStatusLabels[0];
	$parts[] = Text::sprintf('COM_WENDFOLD_MYRSVPS_TRIP_STATUS_META', $statusLabel);

	if (!empty($rsvp->trip_booked_at)) {
		$parts[] = Text::sprintf('COM_WENDFOLD_MYRSVPS_TRIP_BOOKED_META', HTMLHelper::_('date', (string) $rsvp->trip_booked_at, 'M j, Y'));
	}

	return implode(' - ', array_filter($parts));
};

ob_start();
?>
<a class="<?php echo hcButtonClasses('back'); ?>" href="<?php echo htmlspecialchars($dashUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_MYRSVPS_BACK_DASHBOARD'); ?>
</a>
<?php
$headerActions = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_MYRSVPS_PAGE_TITLE'),
	'actionsHtml' => $headerActions,
];
?>
<div class="hc-page hc-page--myrsvps hc-myrsvps">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MYRSVPS_UPCOMING_HEADING'); ?></h2>
				</div>

				<div class="hc-panel__actions">
					<a class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>" href="<?php echo htmlspecialchars($eventsUrl, ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo Text::_('COM_WENDFOLD_DASHBOARD_LINK_EVENTS'); ?>
					</a>
				</div>
			</div>

			<div class="hc-panel__body">
				<?php if (empty($upcoming)) : ?>
					<p class="hc-myrsvps-empty mb-0">
						<?php echo Text::_('COM_WENDFOLD_MYRSVPS_NO_UPCOMING'); ?>
					</p>
				<?php else : ?>
					<div class="hc-event-list mb-0">
						<?php foreach ($upcoming as $rsvp) : ?>
							<?php
							$url = $routeToEventDetail($rsvp);
							$type = [
								'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($rsvp->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
								'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($rsvp->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							];
							$date = new DateTime((string) $rsvp->event_date);
							$loc = trim((string) ($rsvp->location_city ?? $rsvp->event_city ?? '') . (($rsvp->location_state ?? $rsvp->event_state ?? '') ? ', ' . ($rsvp->location_state ?? $rsvp->event_state) : ''));
							$canCancel = !empty($rsvp->can_cancel);
							$isRc = !empty($rsvp->is_road_captain) || !empty($rsvp->is_group_road_captain);
							$isSweep = !empty($rsvp->is_sweep) || !empty($rsvp->is_group_sweep);
							$isMeeting = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isMeeting((int) ($rsvp->event_behavior ?? 0), (int) ($rsvp->workflow_mode ?? 0)); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							$meetingSubtypeLabel = $isMeeting ? \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($rsvp->event_type_title ?? '')) : ''; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							$groupMeta = $renderGroupMeta($rsvp);
							$volunteerMeta = $renderVolunteerMeta($rsvp);
							$attendanceMeta = $renderAttendanceMeta($rsvp);
							$tripMeta = $renderTripMeta($rsvp);
							?>
							<div class="hc-event-row hc-event-row--stacked">
								<div class="hc-date-cal">
									<div class="hc-date-cal-mon"><?php echo $date->format('M'); ?></div>
									<div class="hc-date-cal-day"><?php echo $date->format('j'); ?></div>
								</div>
								<div class="hc-event-info">
									<a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="hc-event-title hc-event-title-link">
										<?php echo htmlspecialchars((string) $rsvp->title, ENT_QUOTES, 'UTF-8'); ?>
									</a>
									<div class="hc-event-loc"><?php echo DateOnly::format((string) $rsvp->event_date, 'D, M j, Y'); ?><?php echo $loc ? ' - ' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') : ''; ?></div>
									<?php if ($attendanceMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($attendanceMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($tripMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($tripMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($groupMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($groupMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($volunteerMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($volunteerMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
								</div>
								<div class="hc-event-meta">
									<span class="hc-badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8'); ?></span>
									<?php if ($isMeeting) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars($meetingSubtypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->has_attendance)) : ?>
										<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_MEETING_STATUS_PRESENT'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->group_title)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars((string) $rsvp->group_title, ENT_QUOTES, 'UTF-8'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_road_captain)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_sweep)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_SWEEP'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_group_road_captain) && empty($rsvp->is_road_captain)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_RC'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_group_sweep) && empty($rsvp->is_sweep)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_SWEEP'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_trip_lead)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEAD'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_trip_sweep)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SWEEP'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->volunteer_role_title)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SECTION'); ?></span>
									<?php endif; ?>
								</div>
								<?php if ($canCancel) : ?>
									<form method="post" action="<?php echo htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8'); ?>" class="hc-event-actions">
										<input type="hidden" name="event_id" value="<?php echo (int) $rsvp->event_id; ?>">
										<?php echo HTMLHelper::_('form.token'); ?>
										<button type="submit" class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?>"
											data-confirm="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_MYRSVPS_CANCEL_CONFIRM')); ?>">
											<?php echo Text::_('COM_WENDFOLD_MYRSVPS_CANCEL_BTN'); ?>
										</button>
									</form>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MYRSVPS_PAST_HEADING'); ?></h2>
				</div>
			</div>

			<div class="hc-panel__body">
				<?php if (empty($past)) : ?>
					<p class="hc-myrsvps-empty mb-0">
						<?php echo Text::_('COM_WENDFOLD_MYRSVPS_NO_PAST'); ?>
					</p>
				<?php else : ?>
					<div class="hc-event-list hc-event-list-past mb-0">
						<?php foreach ($past as $rsvp) : ?>
							<?php
							$url = $routeToEventDetail($rsvp);
							$type = [
								'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($rsvp->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
								'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($rsvp->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							];
							$date = new DateTime((string) $rsvp->event_date);
							$loc = trim((string) ($rsvp->location_city ?? $rsvp->event_city ?? '') . (($rsvp->location_state ?? $rsvp->event_state ?? '') ? ', ' . ($rsvp->location_state ?? $rsvp->event_state) : ''));
							$isRc = !empty($rsvp->is_road_captain) || !empty($rsvp->is_group_road_captain);
							$isSweep = !empty($rsvp->is_sweep) || !empty($rsvp->is_group_sweep);
							$isMeeting = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isMeeting((int) ($rsvp->event_behavior ?? 0), (int) ($rsvp->workflow_mode ?? 0)); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							$meetingSubtypeLabel = $isMeeting ? \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($rsvp->event_type_title ?? '')) : ''; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							$groupMeta = $renderGroupMeta($rsvp);
							$volunteerMeta = $renderVolunteerMeta($rsvp);
							$attendanceMeta = $renderAttendanceMeta($rsvp);
							$tripMeta = $renderTripMeta($rsvp);
							?>
							<a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="hc-event-row hc-event-row--stacked">
								<div class="hc-date-cal">
									<div class="hc-date-cal-mon"><?php echo $date->format('M'); ?></div>
									<div class="hc-date-cal-day"><?php echo $date->format('j'); ?></div>
								</div>
								<div class="hc-event-info">
									<div class="hc-event-title"><?php echo htmlspecialchars((string) $rsvp->title, ENT_QUOTES, 'UTF-8'); ?></div>
									<div class="hc-event-loc"><?php echo DateOnly::format((string) $rsvp->event_date, 'D, M j, Y'); ?><?php echo $loc ? ' - ' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') : ''; ?></div>
									<?php if ($attendanceMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($attendanceMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($tripMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($tripMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($groupMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($groupMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($volunteerMeta !== '') : ?>
										<div class="hc-event-submeta"><?php echo htmlspecialchars($volunteerMeta, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
								</div>
								<div class="hc-event-meta">
									<span class="hc-badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8'); ?></span>
									<?php if ($isMeeting) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars($meetingSubtypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->has_attendance)) : ?>
										<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_MEETING_STATUS_PRESENT'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->group_title)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars((string) $rsvp->group_title, ENT_QUOTES, 'UTF-8'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_road_captain)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_sweep)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_SWEEP'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_group_road_captain) && empty($rsvp->is_road_captain)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_RC'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_group_sweep) && empty($rsvp->is_sweep)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_SWEEP'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_trip_lead)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEAD'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->is_trip_sweep)) : ?>
										<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SWEEP'); ?></span>
									<?php endif; ?>
									<?php if (!empty($rsvp->volunteer_role_title)) : ?>
										<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SECTION'); ?></span>
									<?php endif; ?>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</div>
</div>

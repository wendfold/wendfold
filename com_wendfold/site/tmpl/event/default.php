<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$item = $this->item;
$roster = $this->roster;
$isRcSweep = $this->isRcOrSweep ?? false;
$isRsvped = $this->userIsRsvped ?? false;
$rsvpCount = $this->rsvpCount ?? 0;
$attendanceCount = $this->attendanceCount ?? 0;
$rideGroups = $this->rideGroups ?? [];
$userRideGroup = $this->userRideGroup ?? null;
$parentEvent = $this->parentEvent ?? null;
$childEvents = $this->childEvents ?? [];
$volunteerRoles = $this->volunteerRoles ?? [];
$tripParticipantStatus = $this->tripParticipantStatus ?? null;
$volunteerAssignment = $this->volunteerAssignment ?? null;
$canManageVolunteer = !empty($this->canManageVolunteer);
$canManageMeetingAttendance = !empty($this->canManageMeetingAttendance);
$user = Factory::getApplication()->getIdentity();
$canViewRiders = (bool) $user->id;

$type = [
	'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($item->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
	'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($item->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
];
$routeToEventDetail = static function ($eventLike): string {
	return RouteHelper::detail($eventLike);
};
$routeToTripPlanner = static function ($eventLike): string {
	return RouteHelper::trip((int) ($eventLike->id ?? 0));
};

$status = (int) ($item->status ?? 1);
$cancelled = $status === 3;
$completed = $status === 2;
$confirmed = $status === 4;
$weather = $status === 5;
$workflowMode = (int) ($item->workflow_mode ?? 1);
$eventOpen = in_array($status, [1, 4], true);
$siteTime = SiteTime::fromApplication();
$isPast = $siteTime->eventsListIncludes((string) ($item->event_date ?? ''), 'past');
$allowRsvp = !empty($item->allow_rsvp);
$rsvpOpen = $allowRsvp && in_array($status, [1, 4, 5], true) && !$isPast;

$capacity = (int) ($item->capacity ?? 0);
$isFull = ($capacity > 0) && ($rsvpCount >= $capacity);
$isOvernight = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($item->event_behavior ?? 0), $workflowMode); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
$showRide = !empty($item->is_riding_event);
$isMeeting = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isMeeting((int) ($item->event_behavior ?? 0), $workflowMode); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
$isOvernightParent = $isOvernight && empty($item->parent_event_id);
$isVolunteerWorkflow = $workflowMode === 4;
$hasVolunteerRoles = !empty($volunteerRoles);
$sidebarOwnsHeaderDetails = $isOvernightParent || $isVolunteerWorkflow || $hasVolunteerRoles;
$meetingSubtypeLabel = $isMeeting
	? \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($item->event_type_title ?? '')) // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
	: '';

$ksuName = $item->ksu_display ?? ($item->ksu_location_custom ?? '');
$ksuAddress = trim((string) ($item->ksu_addr ?? ($item->ksu_address ?? '')));
$ksuCity = trim((string) ($item->ksu_city ?? ''));
$ksuState = trim((string) ($item->ksu_state ?? ''));
$ksuZip = trim((string) ($item->ksu_zip ?? ''));

$ksdName = $item->ksd_display ?? ($item->ksd_location_custom ?? '');
$ksdCity = trim((string) ($item->ksd_city ?? ''));
$ksdState = trim((string) ($item->ksd_state ?? ''));

if ($ksdCity === '' && $ksdState === '') {
	$ksuId = (int) ($item->ksu_location_id ?? 0);
	$ksdId = (int) ($item->ksd_location_id ?? 0);
	if ($ksuId > 0 && $ksuId === $ksdId) {
		$ksdCity = $ksuCity;
		$ksdState = $ksuState;
	}
}

if ($ksdCity === '' && $ksdState === '' && ($ksuCity !== '' || $ksuState !== '')) {
	$ksdCity = $ksuCity;
	$ksdState = $ksuState;
}

$evtName = $item->evt_display ?? ($item->event_location_custom ?? '');
$evtAddress = trim((string) ($item->evt_addr ?? ($item->event_address ?? '')));
$evtCity = trim((string) ($item->evt_city ?? ($item->event_city ?? '')));
$evtState = trim((string) ($item->evt_state ?? ($item->event_state ?? '')));
$evtZip = trim((string) ($item->evt_zip ?? ''));
$destinationCity = trim($evtCity . ($evtState ? ', ' . $evtState : ''));

if ($ksuAddress && $ksuCity) {
	$mapQuery = $ksuAddress . ', ' . $ksuCity
		. ($ksuState ? ', ' . $ksuState : '')
		. ($ksuZip ? ' ' . $ksuZip : '');
} else {
	$mapQuery = '';
}

$gpxUrl = (string) ($item->gpxUrl ?? '');
$gpxHref = htmlspecialchars($gpxUrl, ENT_QUOTES, 'UTF-8');
$gpxClass = hcButtonClasses('secondary');
$osmTiles = (int) ComponentHelper::getParams('com_wendfold')->get('osm_tiles', 1) === 1;
$osmNoticeKey = $osmTiles ? 'COM_WENDFOLD_EVENT_OSM_NOTICE' : 'COM_WENDFOLD_EVENT_OSM_DISABLED';
$itemFields = get_object_vars($item);
$canRsvpByScope = !empty($itemFields['canRsvpByScope']);
$ksuLocLat = $itemFields['ksu_loc_lat'] ?? null;
$ksuLocLng = $itemFields['ksu_loc_lng'] ?? null;
$hasKsuCoords = is_numeric($ksuLocLat) && is_numeric($ksuLocLng);
$showKsuMarker = $osmTiles && $hasKsuCoords;
$ksuMapInRoute = $showRide && $gpxUrl === '' && $showKsuMarker;
$vendorBase = rtrim(Uri::root(true), '/') . '/media/com_wendfold/vendor/';
$leafletImages = $vendorBase . 'leaflet/images/';
$gpxIconBase = $vendorBase . 'leaflet-gpx/';
$hdLink1 = trim((string) ($item->route_link ?? ''));
$hdLink2 = trim((string) ($item->route_link_2 ?? ''));

$rsvpAction = RouteHelper::task('event.rsvp');
$volunteerAction = RouteHelper::task('event.volunteer');
$tripStatusAction = RouteHelper::task('event.tripstatus');
$returnUrl = RouteHelper::encodeReturn(RouteHelper::detailInternal($item));
$rcUrl = RouteHelper::rcAttendance((int) $item->id);
$volunteerBoardUrl = RouteHelper::volunteerBoard((int) $item->id);
$meetingAttendanceUrl = RouteHelper::meetingAttendance((int) $item->id);

$diffLabels = [
	1 => Text::_('COM_WENDFOLD_EVENT_DIFFICULTY_EASY'),
	2 => Text::_('COM_WENDFOLD_EVENT_DIFFICULTY_MODERATE'),
	3 => Text::_('COM_WENDFOLD_EVENT_DIFFICULTY_CHALLENGING'),
];
$diffColors = [1 => 'hc-badge-social', 2 => 'hc-badge-warning', 3 => 'hc-badge-danger'];

$bookingMethodLabels = [
	'online' => Text::_('COM_WENDFOLD_TRIP_BOOKING_METHOD_ONLINE'),
	'phone' => Text::_('COM_WENDFOLD_TRIP_BOOKING_METHOD_PHONE'),
	'group_block' => Text::_('COM_WENDFOLD_TRIP_BOOKING_METHOD_GROUP_BLOCK'),
	'manual' => Text::_('COM_WENDFOLD_TRIP_BOOKING_METHOD_MANUAL'),
];

$tripStatusLabels = [
	0 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOT_SET'),
	1 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_INTERESTED'),
	2 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_BOOKING'),
	3 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_BOOKED'),
	4 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_OWN'),
	5 => Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOT_NEEDED'),
];

$rcPhoto = !empty($item->road_captain_photo) ? Uri::root() . ltrim((string) $item->road_captain_photo, '/') : '';
$swPhoto = !empty($item->sweep_photo) ? Uri::root() . ltrim((string) $item->sweep_photo, '/') : '';

$showManageCheckin = $isRcSweep && $eventOpen && !$completed && !$cancelled;
$showManageMeetingAttendance = $isMeeting && $canManageMeetingAttendance;
$meetingRollCallEditable = in_array($status, [1, 4, 5], true) && !$isPast;
$showManageVolunteerBoard = $canManageVolunteer && $hasVolunteerRoles;
$showRideGroups = $showRide && $canViewRiders && !empty($rideGroups);

$visibleRiders = [];
foreach ($roster as $rider) {
	$profileId = (int) ($rider->profile_id ?? 0);

	if ($profileId > 0 && ($profileId === (int) ($item->road_captain_id ?? 0) || $profileId === (int) ($item->sweep_id ?? 0))) {
		continue;
	}

	$visibleRiders[] = $rider;
}

$showRosterPanel = $canViewRiders && ($showRide || $allowRsvp || !empty($visibleRiders) || ($isMeeting && ($attendanceCount > 0 || $showManageMeetingAttendance)));
$rosterPanelTitle = $isMeeting
	? Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_TITLE')
	: ($showRide ? Text::_('COM_WENDFOLD_EVENT_WHOS_RIDING') : Text::_('COM_WENDFOLD_EVENT_WHOS_GOING'));

$renderGroupSummary = static function ($group): string {
	if (!$group) {
		return '';
	}

	$parts = [];

	if (!empty($group->road_captain_name)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN') . ': ' . (string) $group->road_captain_name;
	}

	if (!empty($group->sweep_name)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_SWEEP') . ': ' . (string) $group->sweep_name;
	}

	if (!empty($group->staging_hint)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_GROUP_STAGING_HINT') . ': ' . (string) $group->staging_hint;
	}

	if (!empty($group->order_hint)) {
		$parts[] = Text::_('COM_WENDFOLD_EVENT_GROUP_ORDER_HINT') . ': ' . (string) $group->order_hint;
	}

	return implode(' - ', $parts);
};

$statusBadge = '';
$statusBannerClass = '';
$statusBannerText = '';

if ($cancelled) {
	$statusBadge = Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED');
	$statusBannerClass = 'alert-danger';
	$statusBannerText = Text::_('COM_WENDFOLD_EVENT_STATUS_BANNER_CANCELLED');
} elseif ($completed) {
	$statusBadge = Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED');
	$statusBannerClass = 'alert-secondary';
	$statusBannerText = Text::_('COM_WENDFOLD_EVENT_STATUS_BANNER_COMPLETED');
} elseif ($weather) {
	$statusBadge = Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH');
	$statusBannerClass = 'alert-warning';
	$statusBannerText = Text::_('COM_WENDFOLD_EVENT_STATUS_BANNER_WEATHER_WATCH');
} elseif ($confirmed) {
	$statusBadge = Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED');
	$statusBannerClass = 'alert-success';
	$statusBannerText = Text::_('COM_WENDFOLD_EVENT_STATUS_BANNER_CONFIRMED');
}

$eventDateLabel = DateOnly::format($item->event_date, 'l, F j, Y');
$ksuTimeLabel = !empty($item->ksu_time) ? date('g:i A', strtotime((string) $item->ksu_time)) : '';

$headerMeta = $sidebarOwnsHeaderDetails ? [] : [$eventDateLabel];
if (!$sidebarOwnsHeaderDetails && $showRide && $ksuTimeLabel !== '') {
	$headerMeta[] = [
		'html' => OutputEscape::termWithTooltipAndValue(
			Text::_('COM_WENDFOLD_EVENT_KSU'),
			Text::_('COM_WENDFOLD_EVENT_KSU_DESC'),
			$ksuTimeLabel
		),
	];
}
if (!$sidebarOwnsHeaderDetails && $ksuName !== '') {
	$headerMeta[] = $ksuName;
}


$meetingCountMeta = '';
if ($isMeeting) {
	$meetingCountMeta = !empty($allowRsvp)
		? Text::sprintf('COM_WENDFOLD_MEETING_ROSTER_META_WITH_PLANNED', (int) $attendanceCount, (int) $rsvpCount)
		: Text::sprintf('COM_WENDFOLD_MEETING_ROSTER_META', (int) $attendanceCount);
}

$headerCountMeta = $isMeeting
	? $meetingCountMeta
	: (($showManageCheckin || $rsvpOpen || $showManageVolunteerBoard) && $rsvpCount > 0
		? Text::sprintf($rsvpCount === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', $rsvpCount)
		: '');

$rosterMetaText = $isMeeting
	? $meetingCountMeta
	: (!empty($visibleRiders)
		? Text::sprintf(count($visibleRiders) === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', count($visibleRiders))
		: '');

$rosterStatusLiveText = $isMeeting ? Text::_('COM_WENDFOLD_MEETING_ROLLCALL_LIVE') : Text::_('COM_WENDFOLD_EVENT_WHOS_RIDING_LIVE');
$rosterStatusFinalText = $isMeeting ? Text::_('COM_WENDFOLD_MEETING_ROLLCALL_FINAL') : Text::_('COM_WENDFOLD_EVENT_WHOS_RIDING_FINAL');
$rosterEmptyText = $isMeeting ? Text::_('COM_WENDFOLD_MEETING_ROLLCALL_NONE') : Text::_('COM_WENDFOLD_EVENT_NO_CHECKINS');

ob_start();
?>
<div class="hc-event-rsvp hc-trip-header-actions">
	<div class="hc-trip-header-actions__primary">
		<?php if ($parentEvent) : ?>
			<a href="<?php echo htmlspecialchars($routeToEventDetail($parentEvent), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_PARENT_TRIP_VIEW'); ?></a>
		<?php elseif ($isOvernightParent) : ?>
			<a href="<?php echo htmlspecialchars($routeToTripPlanner($item), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_OPEN_PLANNER'); ?></a>
		<?php endif; ?>

		<?php if ($showManageVolunteerBoard) : ?>
			<a href="<?php echo htmlspecialchars($volunteerBoardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary'); ?>">
				<?php echo Text::_('COM_WENDFOLD_EVENT_MANAGE_VOLUNTEERS'); ?>
			</a>
		<?php endif; ?>

		<?php if ($showManageMeetingAttendance) : ?>
			<a href="<?php echo htmlspecialchars($meetingAttendanceUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('action'); ?>">
				<?php echo Text::_($meetingRollCallEditable ? 'COM_WENDFOLD_MEETING_MANAGE_ROLLCALL' : 'COM_WENDFOLD_MEETING_VIEW_ROLLCALL'); ?>
			</a>
		<?php elseif ($showManageCheckin && !$isOvernight) : ?>
			<a href="<?php echo htmlspecialchars($rcUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('action'); ?>">
				<?php echo Text::_('COM_WENDFOLD_EVENT_MANAGE_CHECKIN'); ?>
			</a>
		<?php endif; ?>

		<?php if (!$showManageMeetingAttendance && !($showManageCheckin && !$isOvernight)) : ?>
			<?php if ($allowRsvp && $rsvpOpen && $user->id && $canRsvpByScope) : ?>
				<form action="<?php echo htmlspecialchars($rsvpAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-rsvp-form">
					<input type="hidden" name="event_id" value="<?php echo (int) $item->id; ?>">
					<input type="hidden" name="going" value="<?php echo $isRsvped ? '0' : '1'; ?>">
					<input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
					<?php echo HTMLHelper::_('form.token'); ?>
					<button type="submit" class="<?php echo hcButtonClasses($isRsvped ? 'danger' : 'action'); ?>"
						<?php echo ($isFull && !$isRsvped) ? 'disabled aria-disabled="true"' : ''; ?>>
						<?php echo $isRsvped
							? Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_REMOVE_BUTTON' : 'COM_WENDFOLD_EVENT_RSVP_REMOVE')
							: Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_ADD_BUTTON' : 'COM_WENDFOLD_EVENT_RSVP_ADD'); ?>
					</button>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if (
		(!$showManageMeetingAttendance && !($showManageCheckin && !$isOvernight) && (!$allowRsvp || (!$user->id && $rsvpOpen) || ($isFull && !$isRsvped)))
		|| $headerCountMeta !== ''
	) : ?>
		<div class="hc-trip-header-actions__meta">
			<?php if (!$showManageMeetingAttendance && !($showManageCheckin && !$isOvernight)) : ?>
				<?php if (!$allowRsvp) : ?>
					<span class="hc-rsvp-meta"><?php echo Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_ROLLCALL_ONLY' : 'COM_WENDFOLD_EVENT_RSVP_DISABLED'); ?></span>
				<?php elseif ($rsvpOpen && !$user->id) : ?>
					<span class="hc-rsvp-meta"><?php echo Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_LOGIN_TO_RESPOND' : 'COM_WENDFOLD_EVENT_LOGIN_TO_RSVP'); ?></span>
				<?php elseif ($isFull && !$isRsvped) : ?>
					<span class="hc-rsvp-meta"><?php echo Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_FULL' : 'COM_WENDFOLD_RSVP_EVENT_FULL'); ?></span>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ($headerCountMeta !== '') : ?>
				<span class="hc-rsvp-meta"><?php echo $headerCountMeta; ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
<?php
$headerActions = trim((string) ob_get_clean());

ob_start();
?>
<span class="hc-badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php if ($isMeeting) : ?>
	<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars($meetingSubtypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
	<?php if (!$allowRsvp) : ?>
		<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_MEETING_ROLLCALL_ONLY_BADGE'); ?></span>
	<?php endif; ?>
<?php endif; ?>
<?php if ($statusBadge !== '') : ?>
	<span class="hc-badge <?php echo htmlspecialchars($cancelled ? 'hc-badge-danger' : ($completed ? 'hc-badge-neutral' : ($weather ? 'hc-badge-warning' : 'hc-badge-success')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endif; ?>
<?php
$headerBadges = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => (string) $item->title,
	'subtitle' => $sidebarOwnsHeaderDetails ? '' : ($destinationCity !== '' ? $destinationCity : (string) $evtName),
	'metaItems' => $headerMeta,
	'badgesHtml' => $sidebarOwnsHeaderDetails ? '' : $headerBadges,
	'actionsHtml' => $headerActions,
];
?>

<div class="hc-page hc-page--event hc-event-page">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<?php if ($statusBannerText !== '') : ?>
		<div class="alert <?php echo htmlspecialchars($statusBannerClass, ENT_QUOTES, 'UTF-8'); ?> hc-event-status-banner mb-0">
			<?php echo $statusBannerText; ?>
		</div>
	<?php endif; ?>

	<div class="hc-page__layout hc-page__layout--sidebar hc-event-layout">
		<section class="hc-panel hc-event-layout__overview">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_OVERVIEW'); ?></h2>
				</div>
			</div>

			<div class="hc-panel__body">
				<?php if (OutputEscape::hasVisibleContent((string) ($item->description ?? ''))) : ?>
					<div class="hc-desc" data-hc-desc>
						<?php echo HTMLHelper::_('content.prepare', (string) $item->description); ?>
					</div>
					<button type="button" class="hc-desc-toggle" data-hc-desc-toggle
						data-more="<?php echo Text::_('COM_WENDFOLD_EVENT_DESC_MORE'); ?>"
						data-less="<?php echo Text::_('COM_WENDFOLD_EVENT_DESC_LESS'); ?>">
						<?php echo Text::_('COM_WENDFOLD_EVENT_DESC_MORE'); ?>
					</button>
				<?php else : ?>
					<p class="hc-empty mb-0"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<?php if ($parentEvent) : ?>
			<section class="hc-panel hc-event-layout__trip-parent">
				<div class="hc-panel__header"><div><h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_PARENT_TRIP'); ?></h2></div></div>
				<div class="hc-panel__body">
					<p class="mb-2"><?php echo htmlspecialchars((string) ($parentEvent->title ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
					<a href="<?php echo htmlspecialchars($routeToEventDetail($parentEvent), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_PARENT_TRIP_VIEW'); ?></a>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($isOvernight) : ?>
			<section class="hc-panel hc-event-layout__trip">
				<div class="hc-panel__header"><div><h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_OVERVIEW'); ?></h2></div></div>
				<div class="hc-panel__body hc-stack">
					<div>
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_DATES'); ?></div>
						<div><?php echo htmlspecialchars(DateOnly::format($item->start_date ?: $item->event_date, 'M j, Y') . (!empty($item->end_date) && (string) $item->end_date !== (string) ($item->start_date ?: $item->event_date) ? ' - ' . DateOnly::format($item->end_date, 'M j, Y') : ''), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<?php if (!empty($item->trip_lodging_name) || !empty($item->trip_lodging_notes)) : ?>
						<div>
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LODGING'); ?></div>
							<?php if (!empty($item->trip_lodging_name)) : ?><div class="hc-strong"><?php echo htmlspecialchars((string) $item->trip_lodging_name, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
							<?php if (!empty($item->trip_lodging_phone)) : ?><div class="hc-muted"><?php echo htmlspecialchars((string) $item->trip_lodging_phone, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
							<?php if (!empty($item->trip_lodging_address) || !empty($item->trip_lodging_city) || !empty($item->trip_lodging_state) || !empty($item->trip_lodging_zip)) : ?><div class="hc-muted"><?php echo htmlspecialchars(trim((string) $item->trip_lodging_address . (($item->trip_lodging_city || $item->trip_lodging_state) ? ', ' . trim((string) $item->trip_lodging_city . (!empty($item->trip_lodging_state) ? ', ' . (string) $item->trip_lodging_state : '') . (!empty($item->trip_lodging_zip) ? ' ' . (string) $item->trip_lodging_zip : '')) : '')), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
							<?php if (!empty($item->trip_lodging_notes)) : ?><div class="hc-muted"><?php echo nl2br(htmlspecialchars((string) $item->trip_lodging_notes, ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($item->trip_booking_method) || !empty($item->trip_booking_code) || !empty($item->trip_booking_deadline)) : ?>
						<div class="hc-trip-booking-grid">
							<?php if (!empty($item->trip_booking_method)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_METHOD'); ?></span> <?php echo htmlspecialchars((string) ($bookingMethodLabels[(string) $item->trip_booking_method] ?? $item->trip_booking_method), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
							<?php if (!empty($item->trip_booking_code)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_CODE'); ?></span> <?php echo htmlspecialchars((string) $item->trip_booking_code, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
							<?php if (!empty($item->trip_booking_deadline)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_DEADLINE'); ?></span> <?php echo htmlspecialchars(DateOnly::format($item->trip_booking_deadline, 'M j, Y'), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($item->trip_lead_name) || !empty($item->trip_sweep_name)) : ?>
						<div class="hc-trip-booking-grid">
							<?php if (!empty($item->trip_lead_name)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEAD'); ?></span> <?php echo htmlspecialchars((string) $item->trip_lead_name, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
							<?php if (!empty($item->trip_sweep_name)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SWEEP'); ?></span> <?php echo htmlspecialchars((string) $item->trip_sweep_name, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
						</div>
					<?php endif; ?>
					<div>
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS'); ?></div>
						<div class="hc-muted mb-2"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_DESC'); ?></div>
						<?php if (!$user->id) : ?>
							<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_LOGIN'); ?></div>
						<?php elseif (!$isRsvped) : ?>
							<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOT_RSVPED'); ?></div>
						<?php else : ?>
							<form action="<?php echo htmlspecialchars($tripStatusAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-trip-status-form">
								<input type="hidden" name="event_id" value="<?php echo (int) $item->id; ?>">
								<input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo HTMLHelper::_('form.token'); ?>
								<select name="lodging_status" class="form-select mb-2">
									<?php foreach ($tripStatusLabels as $tripStatusValue => $tripStatusLabel) : ?>
										<option value="<?php echo (int) $tripStatusValue; ?>" <?php echo (int) ($tripParticipantStatus->lodging_status ?? 0) === (int) $tripStatusValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($tripStatusLabel, ENT_QUOTES, 'UTF-8'); ?></option>
									<?php endforeach; ?>
								</select>
								<textarea name="lodging_notes" rows="3" class="form-control mb-2" placeholder="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOTES_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($tripParticipantStatus->lodging_notes ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
								<button type="submit" class="<?php echo hcButtonClasses('action', 'hc-btn-sm'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_SAVE'); ?></button>
							</form>
						<?php endif; ?>
					</div>
					<div>
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ITINERARY'); ?></div>
						<?php if (empty($childEvents)) : ?>
							<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ITINERARY_EMPTY'); ?></div>
						<?php else : ?>
							<div class="hc-trip-itinerary">
								<?php foreach ($childEvents as $childEvent) : ?>
									<article class="hc-trip-itinerary__item">
										<div class="hc-trip-itinerary__date"><?php echo htmlspecialchars(DateOnly::format($childEvent->event_date, 'M j'), ENT_QUOTES, 'UTF-8'); ?></div>
										<div class="hc-trip-itinerary__body">
											<div class="hc-strong"><a href="<?php echo RouteHelper::detail($childEvent); ?>"><?php echo htmlspecialchars((string) $childEvent->title, ENT_QUOTES, 'UTF-8'); ?></a></div>
											<?php if (!empty($childEvent->ksu_time)) : ?><div class="hc-muted"><?php echo htmlspecialchars(DateOnly::time((string) $childEvent->ksu_time, 'g:i A'), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
										</div>
									</article>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$volunteerSectionItem = $item;
		$volunteerSectionRoles = $volunteerRoles;
		$volunteerSectionAssignment = $volunteerAssignment;
		$volunteerSectionCanManage = $canManageVolunteer && $hasVolunteerRoles;
		$volunteerSectionAction = $volunteerAction;
		$volunteerSectionReturnUrl = $returnUrl;
		require dirname(__DIR__) . '/_includes/volunteer_section.php';
		?>

		<aside class="hc-page__aside hc-stack hc-event-layout__aside">
			<section class="hc-panel hc-sidebar-card">
				<div class="hc-panel__body">
					<?php if (!$isOvernight) : ?>
						<div class="hc-sidebar-row hc-sidebar-row--first">
							<div class="hc-sidebar-badges">
								<span class="hc-badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8'); ?></span>
								<?php if ($statusBadge !== '') : ?>
									<span class="hc-badge <?php echo htmlspecialchars($cancelled ? 'hc-badge-danger' : ($completed ? 'hc-badge-neutral' : ($weather ? 'hc-badge-warning' : 'hc-badge-success')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8'); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="hc-sidebar-row">
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_DATE'); ?></div>
							<div><?php echo $eventDateLabel; ?></div>
						</div>
					<?php elseif (!empty($item->start_date) || !empty($item->end_date)) : ?>
						<div class="hc-sidebar-row hc-sidebar-row--first">
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_DATES'); ?></div>
							<div>
								<?php echo htmlspecialchars(DateOnly::format($item->start_date ?: $item->event_date, 'M j, Y') . (!empty($item->end_date) && (string) $item->end_date !== (string) ($item->start_date ?: $item->event_date) ? ' - ' . DateOnly::format($item->end_date, 'M j, Y') : ''), ENT_QUOTES, 'UTF-8'); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ($evtName || $evtAddress || $evtCity || $evtState || $evtZip) : ?>
						<div class="hc-sidebar-row">
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_DESTINATION'); ?></div>
							<?php if ($evtName) : ?>
								<div class="hc-strong"><?php echo htmlspecialchars($evtName, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($evtAddress) : ?>
								<div class="hc-muted"><?php echo htmlspecialchars($evtAddress, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($evtCity || $evtState || $evtZip) : ?>
								<div class="hc-muted"><?php echo htmlspecialchars(trim($evtCity . ($evtState ? ', ' . $evtState : '') . ($evtZip ? ' ' . $evtZip : '')), ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ($ksuName || $ksuAddress || $ksuCity || $ksuState || $ksuZip || !empty($item->ksu_time)) : ?>
						<div class="hc-sidebar-row">
							<div class="hc-meta-label"><?php echo OutputEscape::termWithTooltip(Text::_('COM_WENDFOLD_EVENT_KSU'), Text::_('COM_WENDFOLD_EVENT_KSU_DESC')); ?></div>
							<?php if ($ksuTimeLabel !== '') : ?>
								<div class="hc-strong"><?php echo htmlspecialchars($ksuTimeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($ksuName) : ?>
								<div class="hc-strong"><?php echo htmlspecialchars($ksuName, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($ksuAddress) : ?>
								<div class="hc-muted"><?php echo htmlspecialchars($ksuAddress, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($ksuCity || $ksuState || $ksuZip) : ?>
								<div class="hc-muted"><?php echo htmlspecialchars(trim($ksuCity . ($ksuState ? ', ' . $ksuState : '') . ($ksuZip ? ' ' . $ksuZip : '')), ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>

							<?php if ($showKsuMarker && !$ksuMapInRoute) : ?>
								<div
									id="hc-ksu-map"
									class="hc-sidebar-map"
									data-lat="<?php echo htmlspecialchars((string) $ksuLocLat, ENT_QUOTES, 'UTF-8'); ?>"
									data-lng="<?php echo htmlspecialchars((string) $ksuLocLng, ENT_QUOTES, 'UTF-8'); ?>"
									data-osm="1"
									data-leaflet-images="<?php echo htmlspecialchars($leafletImages, ENT_QUOTES, 'UTF-8'); ?>"
								></div>
								<p class="hc-muted hc-map-privacy"><?php echo Text::_($osmNoticeKey); ?></p>
							<?php endif; ?>

							<?php if ($mapQuery) : ?>
								<div class="hc-ksu-map">
									<div class="hc-muted hc-map-link">
										<a href="https://maps.google.com/maps?q=<?php echo urlencode($mapQuery); ?>" target="_blank" rel="noopener">
											<?php echo Text::_('COM_WENDFOLD_EVENT_GET_DIRECTIONS'); ?>
										</a>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ($showRide && ($ksdName || $ksdCity || $ksdState || !empty($item->ksd_time))) : ?>
						<div class="hc-sidebar-row">
							<div class="hc-meta-label"><?php echo OutputEscape::termWithTooltip(Text::_('COM_WENDFOLD_EVENT_KSD'), Text::_('COM_WENDFOLD_EVENT_KSD_DESC')); ?></div>
							<?php if (!empty($item->ksd_time)) : ?>
								<div class="hc-strong"><?php echo htmlspecialchars((string) $item->ksd_time, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($ksdName) : ?>
								<div class="hc-strong"><?php echo htmlspecialchars($ksdName, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
							<?php if ($ksdCity || $ksdState) : ?>
								<div class="hc-muted"><?php echo htmlspecialchars(trim($ksdCity . ($ksdState ? ', ' . $ksdState : '')), ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ($showRide && isset($item->distance_credit) && (float) $item->distance_credit > 0) : ?>
						<div class="hc-sidebar-row">
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_DISTANCE'); ?></div>
							<div>~<?php echo number_format((float) $item->distance_credit, 0); ?> mi</div>
						</div>
					<?php endif; ?>

					<?php if (!empty($item->difficulty)) : ?>
						<div class="hc-sidebar-row">
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_DIFFICULTY'); ?></div>
							<span class="hc-badge <?php echo htmlspecialchars($diffColors[(int) $item->difficulty] ?? 'hc-badge-neutral', ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo htmlspecialchars($diffLabels[(int) $item->difficulty] ?? '', ENT_QUOTES, 'UTF-8'); ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<?php if ($showRideGroups && $userRideGroup) : ?>
				<section class="hc-panel hc-sidebar-card hc-event-layout__mygroup">
					<div class="hc-panel__header">
						<div>
							<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_MY_GROUP'); ?></h2>
						</div>
					</div>
					<div class="hc-panel__body">
						<div class="hc-sidebar-row hc-sidebar-row--first">
							<div class="hc-sidebar-badges">
								<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars((string) ($userRideGroup->title ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
							</div>
						</div>
						<?php if (!empty($userRideGroup->road_captain_name)) : ?>
							<div class="hc-sidebar-row">
								<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN'); ?></div>
								<div class="hc-strong"><?php echo htmlspecialchars((string) $userRideGroup->road_captain_name, ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						<?php endif; ?>
						<?php if (!empty($userRideGroup->sweep_name)) : ?>
							<div class="hc-sidebar-row">
								<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_SWEEP'); ?></div>
								<div class="hc-strong"><?php echo htmlspecialchars((string) $userRideGroup->sweep_name, ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						<?php endif; ?>
						<?php if (!empty($userRideGroup->staging_hint)) : ?>
							<div class="hc-sidebar-row">
								<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_GROUP_STAGING_HINT'); ?></div>
								<div><?php echo htmlspecialchars((string) $userRideGroup->staging_hint, ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						<?php endif; ?>
						<?php if (!empty($userRideGroup->order_hint)) : ?>
							<div class="hc-sidebar-row">
								<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_GROUP_ORDER_HINT'); ?></div>
								<div><?php echo htmlspecialchars((string) $userRideGroup->order_hint, ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php elseif ($showRideGroups) : ?>
				<section class="hc-panel hc-sidebar-card hc-event-layout__mygroup">
					<div class="hc-panel__header">
						<div>
							<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_MY_GROUP'); ?></h2>
						</div>
					</div>
					<div class="hc-panel__body">
						<p class="hc-empty mb-0"><?php echo Text::_('COM_WENDFOLD_EVENT_MY_GROUP_UNASSIGNED'); ?></p>
					</div>
				</section>
			<?php endif; ?>
		</aside>

		<?php if ($showRide && ($hdLink1 || $hdLink2 || $gpxUrl || $ksuMapInRoute)) : ?>
			<section class="hc-panel hc-event-layout__route">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_ROUTE_MAP'); ?></h2>
					</div>
				</div>

				<div class="hc-panel__body">
					<?php if ($hdLink1 || $hdLink2 || $gpxUrl) : ?>
						<div class="hc-detail-actions mb-3">
							<?php if ($hdLink1) : ?>
								<a href="<?php echo htmlspecialchars($hdLink1, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="<?php echo hcButtonClasses('secondary'); ?>">
									<?php echo Text::_('COM_WENDFOLD_EVENT_ROUTE_LINK'); ?>
								</a>
							<?php endif; ?>
							<?php if ($hdLink2) : ?>
								<a href="<?php echo htmlspecialchars($hdLink2, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="<?php echo hcButtonClasses('secondary'); ?>">
									<?php echo Text::_('COM_WENDFOLD_EVENT_ROUTE_LINK_2'); ?>
								</a>
							<?php endif; ?>
							<?php if ($gpxUrl) : ?>
								<a href="<?php echo $gpxHref; ?>" download="route.gpx" class="<?php echo $gpxClass; ?>">
									<?php echo Text::_('COM_WENDFOLD_EVENT_DOWNLOAD_GPX'); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ($gpxUrl) : ?>
						<div class="hc-map-section-label"><?php echo Text::_('COM_WENDFOLD_EVENT_ROUTE_MAP'); ?></div>
						<div
							id="hc-route-map"
							class="hc-map-gpx"
							data-gpx-url="<?php echo htmlspecialchars($gpxUrl, ENT_QUOTES, 'UTF-8'); ?>"
							data-osm="<?php echo $osmTiles ? '1' : '0'; ?>"
							data-leaflet-images="<?php echo htmlspecialchars($leafletImages, ENT_QUOTES, 'UTF-8'); ?>"
							data-icon-base="<?php echo htmlspecialchars($gpxIconBase, ENT_QUOTES, 'UTF-8'); ?>"
						></div>
						<p class="hc-muted hc-map-privacy"><?php echo Text::_($osmNoticeKey); ?></p>
					<?php elseif ($ksuMapInRoute) : ?>
						<div class="hc-map-section-label"><?php echo OutputEscape::termWithTooltip(Text::_('COM_WENDFOLD_EVENT_KSU'), Text::_('COM_WENDFOLD_EVENT_KSU_DESC')); ?></div>
						<div
							id="hc-ksu-map"
							class="hc-map-gpx"
							data-lat="<?php echo htmlspecialchars((string) $ksuLocLat, ENT_QUOTES, 'UTF-8'); ?>"
							data-lng="<?php echo htmlspecialchars((string) $ksuLocLng, ENT_QUOTES, 'UTF-8'); ?>"
							data-osm="1"
							data-leaflet-images="<?php echo htmlspecialchars($leafletImages, ENT_QUOTES, 'UTF-8'); ?>"
						></div>
						<p class="hc-muted hc-map-privacy"><?php echo Text::_($osmNoticeKey); ?></p>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if (!empty($item->road_captain_name) || !empty($item->sweep_name)) : ?>
			<section class="hc-panel hc-event-layout__organizers">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_ORGANIZERS'); ?></h2>
					</div>
				</div>

				<div class="hc-panel__body hc-detail-officers">
					<div class="hc-officer-grid">
						<?php if (!empty($item->road_captain_name)) : ?>
							<div class="hc-officer-card">
								<?php if ($rcPhoto) : ?>
									<img class="hc-officer-photo" src="<?php echo htmlspecialchars($rcPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $item->road_captain_name, ENT_QUOTES, 'UTF-8'); ?>">
								<?php else : ?>
									<div class="hc-officer-photo hc-officer-photo-ph">&#128100;</div>
								<?php endif; ?>
								<div class="hc-officer-text">
									<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN'); ?></div>
									<div class="hc-strong"><?php echo htmlspecialchars((string) $item->road_captain_name, ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
							</div>
						<?php endif; ?>

						<?php if (!empty($item->sweep_name)) : ?>
							<div class="hc-officer-card">
								<?php if ($swPhoto) : ?>
									<img class="hc-officer-photo" src="<?php echo htmlspecialchars($swPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $item->sweep_name, ENT_QUOTES, 'UTF-8'); ?>">
								<?php else : ?>
									<div class="hc-officer-photo hc-officer-photo-ph">&#128100;</div>
								<?php endif; ?>
								<div class="hc-officer-text">
									<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_SWEEP'); ?></div>
									<div class="hc-strong"><?php echo htmlspecialchars((string) $item->sweep_name, ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($showRideGroups) : ?>
			<section class="hc-panel hc-event-layout__groups">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_RIDE_GROUPS'); ?></h2>
						<div class="hc-panel__meta"><?php echo Text::sprintf('COM_WENDFOLD_EVENT_RIDE_GROUPS_META', count($rideGroups)); ?></div>
					</div>
				</div>
				<div class="hc-panel__body">
					<div class="hc-event-group-grid">
						<?php foreach ($rideGroups as $group) : ?>
							<article class="hc-event-group-card">
								<div class="hc-event-group-card__head">
									<h3 class="hc-event-group-card__title"><?php echo htmlspecialchars((string) ($group->title ?? ('Group ' . (int) ($group->group_order ?? 0))), ENT_QUOTES, 'UTF-8'); ?></h3>
									<span class="hc-badge hc-badge-outline"><?php echo Text::sprintf((int) ($group->member_count ?? 0) === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', (int) ($group->member_count ?? 0)); ?></span>
								</div>
								<div class="hc-event-group-card__meta">
									<?php if (!empty($group->road_captain_name)) : ?>
										<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN'); ?></span> <?php echo htmlspecialchars((string) $group->road_captain_name, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if (!empty($group->sweep_name)) : ?>
										<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_SWEEP'); ?></span> <?php echo htmlspecialchars((string) $group->sweep_name, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if (!empty($group->staging_hint)) : ?>
										<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_GROUP_STAGING_HINT'); ?></span> <?php echo htmlspecialchars((string) $group->staging_hint, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if (!empty($group->order_hint)) : ?>
										<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_GROUP_ORDER_HINT'); ?></span> <?php echo htmlspecialchars((string) $group->order_hint, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
								</div>
								<?php if (!empty($group->members)) : ?>
									<div class="hc-event-group-card__members">
										<?php foreach ($group->members as $groupMember) : ?>
											<span class="hc-event-group-chip"><?php echo htmlspecialchars((string) ($groupMember->member_name ?? '--'), ENT_QUOTES, 'UTF-8'); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($showRosterPanel) : ?>
			<section class="hc-panel hc-event-layout__riders">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo $rosterPanelTitle; ?></h2>
						<?php if ($rosterMetaText !== '') : ?>
							<div class="hc-panel__meta"><?php echo $rosterMetaText; ?></div>
						<?php endif; ?>
					</div>

					<div class="hc-panel__actions">
						<?php if ($eventOpen || ($isMeeting && $meetingRollCallEditable)) : ?>
							<span class="hc-badge hc-badge-social"><?php echo $rosterStatusLiveText; ?></span>
						<?php elseif ($completed) : ?>
							<span class="hc-badge hc-badge-neutral"><?php echo $rosterStatusFinalText; ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="hc-panel__body">
					<?php if (empty($visibleRiders)) : ?>
						<p class="hc-muted hc-empty mb-0"><?php echo $rosterEmptyText; ?></p>
					<?php else : ?>
						<div class="hc-event-riders-grid">
							<?php foreach ($visibleRiders as $rider) : ?>
								<?php
								$photoSrc = !empty($rider->photo) ? Uri::root() . ltrim((string) $rider->photo, '/') : '';
								$profileId = (int) ($rider->profile_id ?? 0);
								$isRoadCaptain = $profileId > 0 && $profileId === (int) ($item->road_captain_id ?? 0);
								$isSweepRider = $profileId > 0 && $profileId === (int) ($item->sweep_id ?? 0);
								$isGroupRoadCaptain = $profileId > 0 && $profileId === (int) ($rider->group_road_captain_id ?? 0);
								$isGroupSweep = $profileId > 0 && $profileId === (int) ($rider->group_sweep_id ?? 0);
								$isCheckedIn = !empty($rider->checked_in);
								?>
								<article class="hc-event-rider-card">
									<div class="hc-event-rider-avatar-wrap">
										<?php if ($photoSrc) : ?>
											<img src="<?php echo htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $rider->member_name, ENT_QUOTES, 'UTF-8'); ?>" class="hc-event-rider-avatar">
										<?php else : ?>
											<div class="hc-event-rider-avatar-ph">&#128100;</div>
										<?php endif; ?>
									</div>
									<div class="hc-event-rider-name"><?php echo htmlspecialchars((string) $rider->member_name, ENT_QUOTES, 'UTF-8'); ?></div>
									<div class="hc-event-rider-badges">
										<?php if ($isMeeting) : ?>
											<?php if (!empty($rider->is_officer)) : ?>
												<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_AUDIENCE_OFFICERS'); ?></span>
											<?php endif; ?>
											<?php if (!empty($rider->is_road_captain)) : ?>
												<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_AUDIENCE_ROAD_CAPTAINS'); ?></span>
											<?php endif; ?>
											<?php if ($isCheckedIn) : ?>
												<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_MEETING_STATUS_PRESENT'); ?></span>
											<?php elseif (!empty($rider->is_rsvped)) : ?>
												<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_MEETING_STATUS_PLANNED'); ?></span>
											<?php endif; ?>
										<?php else : ?>
											<?php if ($isRoadCaptain) : ?>
												<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_ROAD_CAPTAIN'); ?></span>
											<?php endif; ?>
											<?php if ($isSweepRider) : ?>
												<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_SWEEP'); ?></span>
											<?php endif; ?>
											<?php if ($isGroupRoadCaptain && !$isRoadCaptain) : ?>
												<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_RC'); ?></span>
											<?php endif; ?>
											<?php if ($isGroupSweep && !$isSweepRider) : ?>
												<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_SWEEP'); ?></span>
											<?php endif; ?>
											<?php if (!empty($rider->group_title)) : ?>
												<span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars((string) $rider->group_title, ENT_QUOTES, 'UTF-8'); ?></span>
											<?php endif; ?>
											<?php if ($isCheckedIn) : ?>
												<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_EVENT_RIDER_STATUS_CHECKED_IN'); ?></span>
											<?php else : ?>
												<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_RIDER_STATUS_RSVP'); ?></span>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$item = $this->item;
$childEvents = $this->childEvents ?? [];
$volunteerRoles = $this->volunteerRoles ?? [];
$tripParticipantStatus = $this->tripParticipantStatus ?? null;
$volunteerAssignment = $this->volunteerAssignment ?? null;
$canManageVolunteer = !empty($this->canManageVolunteer);
$participants = $this->participants ?? [];
$rsvpCount = (int) ($this->rsvpCount ?? 0);
$isRsvped = !empty($this->userIsRsvped);
$canManageCheckin = !empty($this->canManageCheckin);
$user = Factory::getApplication()->getIdentity();
$status = (int) ($item->status ?? 1);
$siteTime = SiteTime::fromApplication();
$isPast = $siteTime->eventsListIncludes((string) ($item->event_date ?? ''), 'past');
$allowRsvp = !empty($item->allow_rsvp);
$rsvpOpen = $allowRsvp && in_array($status, [1, 4, 5], true) && !$isPast;
$showManageCheckin = $canManageCheckin && in_array($status, [1, 4, 5], true);
$capacity = (int) ($item->capacity ?? 0);
$isFull = ($capacity > 0) && ($rsvpCount >= $capacity);

$statusInfo = [
	1 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'class' => 'hc-badge-outline'],
	2 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'), 'class' => 'hc-badge-neutral'],
	3 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'), 'class' => 'hc-badge-danger'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'class' => 'hc-badge-success'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'class' => 'hc-badge-warning'],
];

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

$routeToChildEvent = static function ($event): string {
	return RouteHelper::detail($event);
};

$eventsUrl = RouteHelper::events();
$rawEventUrl = RouteHelper::event((int) $item->id);
$rsvpAction = RouteHelper::task('event.rsvp');
$volunteerAction = RouteHelper::task('event.volunteer');
$tripStatusAction = RouteHelper::task('event.tripstatus');
$volunteerBoardUrl = RouteHelper::volunteerBoard((int) $item->id);
$checkinUrl = RouteHelper::rcAttendance((int) $item->id);
$hasVolunteerRoles = !empty($volunteerRoles);
$returnUrl = RouteHelper::encodeReturn(RouteHelper::tripInternal((int) $item->id));

$eventDateLabel = DateOnly::format($item->event_date, 'l, F j, Y');
$startDate = !empty($item->start_date) ? (string) $item->start_date : (string) $item->event_date;
$endDate = !empty($item->end_date) ? (string) $item->end_date : $startDate;
$tripDateLabel = DateOnly::format($startDate, 'M j, Y');
if ($endDate !== '' && $endDate !== $startDate) {
	$tripDateLabel .= ' - ' . DateOnly::format($endDate, 'M j, Y');
}

$summaryCounts = [
	'participants' => count($participants),
	'itinerary' => count($childEvents),
	'tripOnly' => 0,
	'publicItems' => 0,
	'lodgingComplete' => 0,
	'lodgingPending' => 0,
];

$participantsByStatus = [];
foreach ($tripStatusLabels as $statusValue => $statusLabel) {
	$participantsByStatus[$statusValue] = [
		'label' => $statusLabel,
		'members' => [],
	];
}

foreach ($participants as $participant) {
	$statusValue = (int) ($participant->lodging_status ?? 0);
	$participantsByStatus[$statusValue]['members'][] = $participant;
	if (in_array($statusValue, [3, 4, 5], true)) {
		$summaryCounts['lodgingComplete']++;
	} else {
		$summaryCounts['lodgingPending']++;
	}
}

$itineraryByDate = [];
foreach ($childEvents as $childEvent) {
	$dateKey = (string) ($childEvent->event_date ?? '');
	if ($dateKey === '') {
		$dateKey = 'unknown';
	}
	if (!isset($itineraryByDate[$dateKey])) {
		$itineraryByDate[$dateKey] = [];
	}
	$itineraryByDate[$dateKey][] = $childEvent;
	if (!empty($childEvent->show_in_event_list)) {
		$summaryCounts['publicItems']++;
	} else {
		$summaryCounts['tripOnly']++;
	}
}

$headerMeta = [$tripDateLabel];
if (!empty($item->trip_lodging_name)) {
	$headerMeta[] = (string) $item->trip_lodging_name;
} elseif (!empty($item->evt_display)) {
	$headerMeta[] = (string) $item->evt_display;
}

ob_start();
?>
<div class="hc-detail-actions hc-trip-header-actions">
	<div class="hc-trip-header-actions__primary">
		<a href="<?php echo htmlspecialchars($rawEventUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VIEW_EVENT_DETAILS'); ?></a>
		<?php if ($showManageCheckin) : ?>
			<a href="<?php echo htmlspecialchars($checkinUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('action'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_MANAGE_CHECKIN'); ?></a>
		<?php endif; ?>
		<?php if ($canManageVolunteer && $hasVolunteerRoles) : ?>
			<a href="<?php echo htmlspecialchars($volunteerBoardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_MANAGE_VOLUNTEERS'); ?></a>
		<?php endif; ?>
	</div>
	<?php if ($rsvpCount > 0) : ?>
		<div class="hc-trip-header-actions__meta">
			<span class="hc-rsvp-meta"><?php echo Text::sprintf($rsvpCount === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', $rsvpCount); ?></span>
		</div>
	<?php endif; ?>
</div>
<?php
$headerActions = trim((string) ob_get_clean());

ob_start();
?>
<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TYPE_OVERNIGHT'); ?></span>
<?php if ($status !== 1) : ?>
	<?php $statusMeta = $statusInfo[$status] ?? $statusInfo[1]; ?>
	<span class="hc-badge <?php echo htmlspecialchars($statusMeta['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusMeta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php endif; ?>
<?php if (!empty($item->trip_booking_deadline)) : ?>
	<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_DEADLINE'); ?>: <?php echo htmlspecialchars(DateOnly::format($item->trip_booking_deadline, 'M j'), ENT_QUOTES, 'UTF-8'); ?></span>
<?php endif; ?>
<?php
$headerBadges = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => (string) $item->title,
	'subtitle' => Text::_('COM_WENDFOLD_EVENT_TRIP_HEADER_SUBTITLE'),
	'metaItems' => $headerMeta,
	'badgesHtml' => $headerBadges,
	'actionsHtml' => $headerActions,
];
?>

<div class="hc-page hc-page--trip hc-trip-page">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel hc-trip-hero">
			<div class="hc-panel__body hc-stack">
				<div class="hc-trip-summary-grid">
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $summaryCounts['participants']; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_PARTICIPANTS'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $summaryCounts['lodgingComplete']; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_BOOKED'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $summaryCounts['lodgingPending']; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_PENDING'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $summaryCounts['itinerary']; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_STOPS'); ?></div>
					</article>
				</div>

				<div class="hc-trip-summary-grid hc-trip-summary-grid--secondary">
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $summaryCounts['tripOnly']; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_TRIP_ONLY'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $summaryCounts['publicItems']; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_PUBLIC'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $rsvpCount; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_RSVPS'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo count($itineraryByDate); ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SUMMARY_DAYS'); ?></div>
					</article>
				</div>

				<?php if (OutputEscape::hasVisibleContent((string) ($item->description ?? ''))) : ?>
					<div class="hc-desc" data-hc-desc>
						<?php echo HTMLHelper::_('content.prepare', (string) $item->description); ?>
					</div>
					<button type="button" class="hc-desc-toggle" data-hc-desc-toggle
						data-more="<?php echo Text::_('COM_WENDFOLD_EVENT_DESC_MORE'); ?>"
						data-less="<?php echo Text::_('COM_WENDFOLD_EVENT_DESC_LESS'); ?>">
						<?php echo Text::_('COM_WENDFOLD_EVENT_DESC_MORE'); ?>
					</button>
				<?php endif; ?>
			</div>
		</section>

		<section class="hc-panel hc-trip-planning">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_PLANNING'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_PLANNING_META'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body hc-trip-planning-grid">
				<article class="hc-trip-detail-card">
					<h3 class="hc-trip-detail-card__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LODGING'); ?></h3>
					<?php if (!empty($item->trip_lodging_name)) : ?><div class="hc-strong"><?php echo htmlspecialchars((string) $item->trip_lodging_name, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (!empty($item->trip_lodging_phone)) : ?><div class="hc-muted"><?php echo htmlspecialchars((string) $item->trip_lodging_phone, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (!empty($item->trip_lodging_address) || !empty($item->trip_lodging_city) || !empty($item->trip_lodging_state) || !empty($item->trip_lodging_zip)) : ?>
						<div class="hc-muted"><?php echo htmlspecialchars(trim((string) $item->trip_lodging_address . (($item->trip_lodging_city || $item->trip_lodging_state) ? ', ' . trim((string) $item->trip_lodging_city . (!empty($item->trip_lodging_state) ? ', ' . (string) $item->trip_lodging_state : '') . (!empty($item->trip_lodging_zip) ? ' ' . (string) $item->trip_lodging_zip : '')) : '')), ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<?php if (!empty($item->trip_lodging_notes)) : ?><div class="hc-muted"><?php echo nl2br(htmlspecialchars((string) $item->trip_lodging_notes, ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
				</article>

				<article class="hc-trip-detail-card">
					<h3 class="hc-trip-detail-card__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING'); ?></h3>
					<?php if (!empty($item->trip_booking_method)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_METHOD'); ?></span> <?php echo htmlspecialchars((string) ($bookingMethodLabels[(string) $item->trip_booking_method] ?? $item->trip_booking_method), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (!empty($item->trip_booking_code)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_CODE'); ?></span> <?php echo htmlspecialchars((string) $item->trip_booking_code, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (!empty($item->trip_booking_deadline)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_DEADLINE'); ?></span> <?php echo htmlspecialchars(DateOnly::format($item->trip_booking_deadline, 'M j, Y'), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (empty($item->trip_booking_method) && empty($item->trip_booking_code) && empty($item->trip_booking_deadline)) : ?><div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_BOOKING_EMPTY'); ?></div><?php endif; ?>
				</article>

				<article class="hc-trip-detail-card">
					<h3 class="hc-trip-detail-card__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEADERSHIP'); ?></h3>
					<?php if (!empty($item->trip_lead_name)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEAD'); ?></span> <?php echo htmlspecialchars((string) $item->trip_lead_name, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (!empty($item->trip_sweep_name)) : ?><div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SWEEP'); ?></span> <?php echo htmlspecialchars((string) $item->trip_sweep_name, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
					<?php if (empty($item->trip_lead_name) && empty($item->trip_sweep_name)) : ?><div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEADERSHIP_EMPTY'); ?></div><?php endif; ?>
				</article>
			</div>
		</section>

		<section class="hc-panel hc-trip-status-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_DESC'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body">
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
		</section>

		<section class="hc-panel hc-trip-itinerary-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ITINERARY'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ITINERARY_META'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body hc-stack">
				<?php if (empty($itineraryByDate)) : ?>
					<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ITINERARY_EMPTY'); ?></div>
				<?php else : ?>
					<?php $dayNumber = 0; ?>
					<?php foreach ($itineraryByDate as $itineraryDate => $dayItems) : ?>
						<?php $dayNumber++; ?>
						<section class="hc-trip-day">
							<div class="hc-trip-day__head">
								<div>
									<h3 class="hc-trip-day__title"><?php echo Text::sprintf('COM_WENDFOLD_EVENT_TRIP_DAY_HEADING', $dayNumber, DateOnly::format($itineraryDate, 'l, M j, Y')); ?></h3>
									<div class="hc-panel__meta"><?php echo Text::sprintf(count($dayItems) === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', count($dayItems)); ?></div>
								</div>
							</div>
							<div class="hc-trip-itinerary-cards">
								<?php foreach ($dayItems as $childEvent) : ?>
									<?php
									$childType = [
										'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($childEvent->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
										'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($childEvent->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
									];
									$childStatus = $statusInfo[(int) ($childEvent->status ?? 1)] ?? $statusInfo[1];
									$locationLabel = trim((string) ($childEvent->location_name ?: $childEvent->event_location_custom ?: $childEvent->ksu_location_name ?: $childEvent->ksu_location_custom ?: ''));
									$cityState = trim((string) ($childEvent->event_city ?: $childEvent->ksu_city ?: '') . ((($childEvent->event_state ?: $childEvent->ksu_state ?: '') !== '') ? ', ' . ($childEvent->event_state ?: $childEvent->ksu_state) : ''));
									$excerpt = OutputEscape::htmlExcerpt((string) ($childEvent->description ?? ''), 140);
									?>
									<article class="hc-trip-itinerary-card">
										<div class="hc-trip-itinerary-card__head">
											<div class="hc-trip-itinerary-card__badges">
												<span class="hc-badge <?php echo htmlspecialchars($childType['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($childType['label'], ENT_QUOTES, 'UTF-8'); ?></span>
												<?php if ((int) ($childEvent->status ?? 1) !== 1) : ?>
													<span class="hc-badge <?php echo htmlspecialchars($childStatus['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($childStatus['label'], ENT_QUOTES, 'UTF-8'); ?></span>
												<?php endif; ?>
												<span class="hc-badge hc-badge-outline"><?php echo !empty($childEvent->show_in_event_list) ? Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_PUBLIC') : Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_TRIP_ONLY'); ?></span>
											</div>
											<a href="<?php echo htmlspecialchars($routeToChildEvent($childEvent), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ITEM_VIEW'); ?></a>
										</div>
										<h4 class="hc-trip-itinerary-card__title"><a href="<?php echo htmlspecialchars($routeToChildEvent($childEvent), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $childEvent->title, ENT_QUOTES, 'UTF-8'); ?></a></h4>
										<div class="hc-trip-itinerary-card__meta">
											<span><?php echo !empty($childEvent->ksu_time)
												? OutputEscape::termWithTooltipAndValue(
													Text::_('COM_WENDFOLD_EVENT_KSU'),
													Text::_('COM_WENDFOLD_EVENT_KSU_DESC'),
													DateOnly::time((string) $childEvent->ksu_time, 'g:i A')
												)
												: Text::_('COM_WENDFOLD_EVENT_TRIP_TIME_TBD'); ?></span>
											<?php if ($locationLabel !== '' || $cityState !== '') : ?>
												<span><?php echo htmlspecialchars(trim($locationLabel . ($cityState !== '' ? ' - ' . $cityState : '')), ENT_QUOTES, 'UTF-8'); ?></span>
											<?php endif; ?>
										</div>
										<?php if ($excerpt !== '') : ?><div class="hc-muted"><?php echo $excerpt; ?></div><?php endif; ?>
									</article>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>

		<?php
		$volunteerSectionItem = $item;
		$volunteerSectionRoles = $volunteerRoles;
		$volunteerSectionAssignment = $volunteerAssignment;
		$volunteerSectionCanManage = $canManageVolunteer && $hasVolunteerRoles;
		$volunteerSectionAction = $volunteerAction;
		$volunteerSectionReturnUrl = $returnUrl;
		require dirname(__DIR__) . '/_includes/volunteer_section.php';
		?>
	</div>

	<aside class="hc-page__aside hc-stack hc-trip-page__aside">
		<section class="hc-panel hc-sidebar-card">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_PARTICIPANTS'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_PARTICIPANTS_META'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body hc-stack">
				<?php if (!$user->id) : ?>
					<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_PARTICIPANTS_LOGIN'); ?></div>
				<?php elseif (empty($participants)) : ?>
					<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_PARTICIPANTS_EMPTY'); ?></div>
				<?php else : ?>
					<?php foreach ($participantsByStatus as $statusGroup) : ?>
						<?php if (empty($statusGroup['members'])) { continue; } ?>
						<section class="hc-trip-participants-group">
							<div class="hc-trip-participants-group__head">
								<h3 class="hc-trip-participants-group__title"><?php echo htmlspecialchars((string) $statusGroup['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
								<span class="hc-badge hc-badge-outline"><?php echo count($statusGroup['members']); ?></span>
							</div>
							<div class="hc-trip-participants-list">
								<?php foreach ($statusGroup['members'] as $participant) : ?>
									<div class="hc-trip-participant-chip">
										<span><?php echo htmlspecialchars((string) ($participant->member_name ?? '--'), ENT_QUOTES, 'UTF-8'); ?></span>
										<span class="hc-trip-participant-chip__badges">
											<?php if (!empty($participant->is_trip_lead)) : ?><span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_LEAD'); ?></span><?php endif; ?>
											<?php if (!empty($participant->is_trip_sweep)) : ?><span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_SWEEP'); ?></span><?php endif; ?>
											<?php if (empty($participant->is_rsvped)) : ?><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_ASSIGNED'); ?></span><?php endif; ?>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>

		<section class="hc-panel hc-sidebar-card">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY'); ?></h2>
				</div>
			</div>
			<div class="hc-panel__body hc-stack">
				<div class="hc-muted"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_DESC'); ?></div>
				<div class="hc-trip-visibility-list">
					<div><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_TRIP_ONLY'); ?></span> <?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_TRIP_ONLY_DESC'); ?></div>
					<div><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_PUBLIC'); ?></span> <?php echo Text::_('COM_WENDFOLD_EVENT_TRIP_VISIBILITY_PUBLIC_DESC'); ?></div>
				</div>
			</div>
		</section>
	</aside>
</div>

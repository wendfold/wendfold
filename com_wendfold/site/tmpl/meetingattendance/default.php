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
use Joomla\CMS\Uri\Uri;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$event = $this->event;
$roster = $this->roster ?? [];
$memberPools = $this->memberPools ?? [];
$attendanceCount = (int) ($this->attendanceCount ?? 0);
$expectedCount = (int) ($this->expectedCount ?? 0);
$eventOpen = !empty($this->eventOpen);
$recommended = $memberPools['recommended'] ?? [];
$others = $memberPools['others'] ?? [];
$poolMode = (string) ($memberPools['mode'] ?? 'standard');
$poolLimitation = (string) ($memberPools['limitation'] ?? '');

$eventUrl = RouteHelper::detail($event);
$addAction = RouteHelper::task('meetingattendance.add');
$removeAction = RouteHelper::task('meetingattendance.remove');

$statusInfo = match ((int) ($event->status ?? 1)) {
	2 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'), 'class' => 'hc-badge-neutral'],
	3 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'), 'class' => 'hc-badge-danger'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'class' => 'hc-badge-success'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'class' => 'hc-badge-warning'],
	default => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'class' => 'hc-badge-social'],
};
$locationLabel = trim((string) ($event->event_location_name ?? $event->event_location_custom ?? ''));
$cityState = trim((string) ($event->event_city ?? '') . (!empty($event->event_state) ? ', ' . (string) $event->event_state : ''));
if ($locationLabel === '') {
	$locationLabel = $cityState;
} elseif ($cityState !== '') {
	$locationLabel .= ' - ' . $cityState;
}
$metaItems = [DateOnly::format((string) $event->event_date, 'l, F j, Y')];
if ($locationLabel !== '') {
	$metaItems[] = $locationLabel;
}

$poolTitle = match ($poolMode) {
	'officers' => Text::_('COM_WENDFOLD_MEETING_POOL_OFFICERS_TITLE'),
	'road_captains' => Text::_('COM_WENDFOLD_MEETING_POOL_ROAD_CAPTAINS_TITLE'),
	'chapter' => Text::_('COM_WENDFOLD_MEETING_POOL_CHAPTER_TITLE'),
	'auxiliary' => Text::_('COM_WENDFOLD_MEETING_POOL_AUXILIARY_TITLE'),
	default => Text::_('COM_WENDFOLD_MEETING_POOL_STANDARD_TITLE'),
};
$poolDescription = match ($poolMode) {
	'officers' => Text::_('COM_WENDFOLD_MEETING_POOL_OFFICERS_DESC'),
	'road_captains' => Text::_('COM_WENDFOLD_MEETING_POOL_ROAD_CAPTAINS_DESC'),
	'chapter' => Text::_('COM_WENDFOLD_MEETING_POOL_CHAPTER_DESC'),
	'auxiliary' => Text::_('COM_WENDFOLD_MEETING_POOL_AUXILIARY_DESC'),
	default => Text::_('COM_WENDFOLD_MEETING_POOL_STANDARD_DESC'),
};

$renderPhoto = static function ($member): string {
	$photoSrc = !empty($member->photo) ? Uri::root() . ltrim((string) $member->photo, '/') : '';

	if ($photoSrc !== '') {
		return '<img src="' . htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8') . '" class="hc-rc-avatar">';
	}

	return '<div class="hc-rc-avatar-ph" aria-hidden="true">&#128100;</div>';
};

$renderBadges = static function ($member, bool $present = false): string {
	$badges = [];

	if ($present) {
		$badges[] = '<span class="hc-badge hc-badge-success">' . htmlspecialchars(Text::_('COM_WENDFOLD_MEETING_STATUS_PRESENT'), ENT_QUOTES, 'UTF-8') . '</span>';
	} elseif (!empty($member->has_rsvp) || !empty($member->is_rsvped)) {
		$badges[] = '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_MEETING_STATUS_PLANNED'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	if (!empty($member->is_officer)) {
		$badges[] = '<span class="hc-badge hc-badge-neutral">' . htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_AUDIENCE_OFFICERS'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	if (!empty($member->is_road_captain)) {
		$badges[] = '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_AUDIENCE_ROAD_CAPTAINS'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	return implode('', $badges);
};

ob_start();
?>
<a class="<?php echo hcButtonClasses('back'); ?>" href="<?php echo htmlspecialchars($eventUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_MEETING_BACK_TO_EVENT'); ?>
</a>
<?php
$headerActions = trim((string) ob_get_clean());

ob_start();
?>
<span class="hc-badge <?php echo htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hc-badge hc-badge-meeting"><?php echo htmlspecialchars(\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($event->event_type_title ?? '')), ENT_QUOTES, 'UTF-8'); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps ?></span>
<?php if (empty($event->allow_rsvp)) : ?>
	<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_MEETING_ROLLCALL_ONLY_BADGE'); ?></span>
<?php endif; ?>
<?php
$headerBadges = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_MEETING_ROLLCALL_PAGE_TITLE'),
	'subtitle' => (string) ($event->title ?? ''),
	'metaItems' => $metaItems,
	'badgesHtml' => $headerBadges,
	'actionsHtml' => $headerActions,
];
?>

<div class="hc-page hc-page--meetingattendance hc-rc-page hc-meeting-page">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<?php if (!$eventOpen) : ?>
		<div class="alert alert-secondary mb-0"><?php echo Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_VIEW_ONLY'); ?></div>
	<?php endif; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MEETING_SUMMARY_TITLE'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_MEETING_SUMMARY_DESC'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body">
				<div class="hc-summary-stats-grid hc-rc-stats-grid">
					<div class="hc-rc-sidebar-stat">
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_MEETING_SUMMARY_PRESENT'); ?></div>
						<div class="hc-stat-value"><?php echo $attendanceCount; ?></div>
					</div>
					<div class="hc-rc-sidebar-stat">
						<div class="hc-meta-label"><?php echo !empty($event->allow_rsvp) ? Text::_('COM_WENDFOLD_MEETING_SUMMARY_LIST') : Text::_('COM_WENDFOLD_MEETING_SUMMARY_MODE'); ?></div>
						<div class="hc-stat-value"><?php echo !empty($event->allow_rsvp) ? $expectedCount : htmlspecialchars(Text::_('COM_WENDFOLD_MEETING_ROLLCALL_ONLY_BADGE'), ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="hc-rc-sidebar-stat">
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_MEETING_SUMMARY_AVAILABLE'); ?></div>
						<div class="hc-stat-value"><?php echo count($recommended) + count($others); ?></div>
					</div>
				</div>
			</div>
		</section>

		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_TITLE'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::sprintf('COM_WENDFOLD_MEETING_ATTENDANCE_META', $attendanceCount); ?></div>
				</div>
			</div>
			<div class="hc-panel__body">
				<?php if (empty($roster)) : ?>
					<div class="hc-rc-empty"><?php echo Text::_('COM_WENDFOLD_MEETING_ROLLCALL_NONE'); ?></div>
				<?php else : ?>
					<div class="hc-rc-list">
						<?php foreach ($roster as $member) : ?>
							<article class="hc-rc-card">
								<div class="hc-rc-member-row">
									<?php echo $renderPhoto($member); ?>
									<div class="hc-rc-member-main">
										<div class="hc-rc-member-mainline">
											<div class="hc-rc-member-name"><?php echo htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
											<div class="hc-rc-member-meta">
												<?php if (!empty($member->member_number)) : ?>
													<span><?php echo Text::_('COM_WENDFOLD_MEMBER_NUMBER'); ?>: <?php echo htmlspecialchars((string) $member->member_number, ENT_QUOTES, 'UTF-8'); ?></span>
												<?php endif; ?>
												<?php if (!empty($member->check_in_time)) : ?>
													<span><?php echo Text::_('COM_WENDFOLD_MEETING_CHECKIN_TIME'); ?>: <?php echo htmlspecialchars(HTMLHelper::_('date', (string) $member->check_in_time, 'g:i A'), ENT_QUOTES, 'UTF-8'); ?></span>
												<?php endif; ?>
											</div>
										</div>
										<div class="hc-rc-flag-list"><?php echo $renderBadges($member, true); ?></div>
									</div>
									<?php if ($eventOpen) : ?>
										<div class="hc-rc-member-actions">
											<form action="<?php echo htmlspecialchars($removeAction, ENT_QUOTES, 'UTF-8'); ?>" method="post">
												<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
												<input type="hidden" name="attendance_id" value="<?php echo (int) ($member->attendance_id ?? 0); ?>">
												<?php echo HTMLHelper::_('form.token'); ?>
												<button type="submit" class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?>" data-confirm="<?php echo OutputEscape::confirm(Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_REMOVE_CONFIRM'), (string) ($member->member_name ?? '')); ?>"><?php echo Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_REMOVE_BUTTON'); ?></button>
											</form>
										</div>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo htmlspecialchars($poolTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
					<div class="hc-panel__meta"><?php echo htmlspecialchars($poolDescription, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body">
				<?php if ($poolLimitation === 'auxiliary') : ?>
					<div class="alert alert-warning"><?php echo Text::_('COM_WENDFOLD_MEETING_POOL_AUXILIARY_LIMITATION'); ?></div>
				<?php endif; ?>

				<?php if (empty($recommended)) : ?>
					<div class="hc-rc-empty"><?php echo Text::_('COM_WENDFOLD_MEETING_POOL_EMPTY'); ?></div>
				<?php else : ?>
					<div class="hc-rc-list">
						<?php foreach ($recommended as $member) : ?>
							<article class="hc-rc-card">
								<div class="hc-rc-member-row">
									<?php echo $renderPhoto($member); ?>
									<div class="hc-rc-member-main">
										<div class="hc-rc-member-mainline">
											<div class="hc-rc-member-name"><?php echo htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
											<div class="hc-rc-member-meta">
												<?php if (!empty($member->member_number)) : ?>
													<span><?php echo Text::_('COM_WENDFOLD_MEMBER_NUMBER'); ?>: <?php echo htmlspecialchars((string) $member->member_number, ENT_QUOTES, 'UTF-8'); ?></span>
												<?php endif; ?>
											</div>
										</div>
										<div class="hc-rc-flag-list"><?php echo $renderBadges($member, false); ?></div>
									</div>
									<?php if ($eventOpen) : ?>
										<div class="hc-rc-member-actions">
											<form action="<?php echo htmlspecialchars($addAction, ENT_QUOTES, 'UTF-8'); ?>" method="post">
												<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
												<input type="hidden" name="profile_id" value="<?php echo (int) ($member->profile_id ?? 0); ?>">
												<?php echo HTMLHelper::_('form.token'); ?>
												<button type="submit" class="<?php echo hcButtonClasses('action', 'hc-btn-sm'); ?>"><?php echo Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_MARK_PRESENT'); ?></button>
											</form>
										</div>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if (!empty($others)) : ?>
			<section class="hc-panel">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MEETING_POOL_MANUAL_TITLE'); ?></h2>
						<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_MEETING_POOL_MANUAL_DESC'); ?></div>
					</div>
				</div>
				<div class="hc-panel__body">
					<div class="hc-rc-list">
						<?php foreach ($others as $member) : ?>
							<article class="hc-rc-card">
								<div class="hc-rc-member-row">
									<?php echo $renderPhoto($member); ?>
									<div class="hc-rc-member-main">
										<div class="hc-rc-member-mainline">
											<div class="hc-rc-member-name"><?php echo htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
											<div class="hc-rc-member-meta">
												<?php if (!empty($member->member_number)) : ?>
													<span><?php echo Text::_('COM_WENDFOLD_MEMBER_NUMBER'); ?>: <?php echo htmlspecialchars((string) $member->member_number, ENT_QUOTES, 'UTF-8'); ?></span>
												<?php endif; ?>
											</div>
										</div>
										<div class="hc-rc-flag-list"><?php echo $renderBadges($member, false); ?></div>
									</div>
									<?php if ($eventOpen) : ?>
										<div class="hc-rc-member-actions">
											<form action="<?php echo htmlspecialchars($addAction, ENT_QUOTES, 'UTF-8'); ?>" method="post">
												<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
												<input type="hidden" name="profile_id" value="<?php echo (int) ($member->profile_id ?? 0); ?>">
												<?php echo HTMLHelper::_('form.token'); ?>
												<button type="submit" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>"><?php echo Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_MARK_PRESENT'); ?></button>
											</form>
										</div>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

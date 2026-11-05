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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$event = $this->event;
$roster = $this->roster;
$members = $this->memberList;
$groups = $this->groups ?? [];
$groupRoleOptions = $this->groupRoleOptions ?? ['road_captains' => [], 'sweeps' => []];
$eventOpen = $this->eventOpen;
$showRideGroups = !empty($event->is_riding_event);
$eventUrl = RouteHelper::detail($event);
$addAction = RouteHelper::task('rcattendance.add');
$setStatusUrl = RouteHelper::task('rcattendance.setstatus');
$createGroupsAction = RouteHelper::task('rcattendance.creategroups');
$addGroupAction = RouteHelper::task('rcattendance.addgroup');
$saveGroupsAction = RouteHelper::task('rcattendance.savegroups');
$assignGroupAction = RouteHelper::task('rcattendance.assigngroup');
$removeAction = RouteHelper::task('rcattendance.remove');
$removeGroupAction = RouteHelper::task('rcattendance.removegroup');
$completeAction = RouteHelper::task('rcattendance.complete');
$cancelAction = RouteHelper::task('rcattendance.cancel');
$eventDate = DateOnly::format($event->event_date, 'D, M j, Y');
$ksuTime = !empty($event->ksu_time) ? DateOnly::time((string) $event->ksu_time, 'g:i A') : '';
$locName = trim((string) ($event->ksu_location_name ?: $event->ksu_location_custom ?: ''));
$cityState = trim((string) ($event->ksu_city ?? '') . (!empty($event->ksu_state) ? ', ' . (string) $event->ksu_state : ''));
$rsvpMembers = $members['rsvp'] ?? [];
$otherMembers = $members['others'] ?? [];
$rosterCount = count($roster);
$completeConfirmAttr = htmlspecialchars(Text::sprintf('COM_WENDFOLD_CHECKIN_CONFIRM_COMPLETE', $rosterCount), ENT_QUOTES, 'UTF-8');
$cancelConfirmAttr = htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_CONFIRM_CANCEL'), ENT_QUOTES, 'UTF-8');
$currentStatus = (int) ($event->status ?? 1);
$canManageStatus = in_array($currentStatus, [1, 4, 5], true);

$statusMap = [
	1 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'class' => 'hc-badge-neutral'],
	2 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'), 'class' => 'hc-badge-neutral'],
	3 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'), 'class' => 'hc-badge-danger'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'class' => 'hc-badge-success'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'class' => 'hc-badge-warning'],
];

$statusButtons = [
	1 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'button' => 'secondary'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'button' => 'action'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'button' => 'danger'],
];

$statusInfo = $statusMap[$currentStatus] ?? $statusMap[1];
$checkedRsvpRows = [];
$walkInRows = [];

foreach ($roster as $member) {
	$isRsvpSource = !empty($member->is_rsvped) || !empty($member->is_assigned_role);

	if ($isRsvpSource) {
		$checkedRsvpRows[(int) $member->profile_id] = $member;
	} else {
		$walkInRows[] = $member;
	}
}

$rsvpRows = [];
foreach ($checkedRsvpRows as $member) {
	$rsvpRows[] = $member;
}
foreach ($rsvpMembers as $member) {
	$rsvpRows[] = $member;
}

usort(
	$rsvpRows,
	static function ($a, $b): int {
		$aChecked = !empty($a->attendance_id) || !empty($a->checked_in);
		$bChecked = !empty($b->attendance_id) || !empty($b->checked_in);
		$aAssigned = ((int) ($a->profile_id ?? 0) > 0) && (!empty($a->is_assigned_role));
		$bAssigned = ((int) ($b->profile_id ?? 0) > 0) && (!empty($b->is_assigned_role));
		$aGroup = (int) ($a->group_order ?? 9999);
		$bGroup = (int) ($b->group_order ?? 9999);
		$aName = strtolower((string) ($a->member_name ?? ''));
		$bName = strtolower((string) ($b->member_name ?? ''));

		return [$bChecked, $bAssigned, $aGroup, $aName] <=> [$aChecked, $aAssigned, $bGroup, $bName];
	}
);

$rsvpTotal = count($rsvpRows);
$checkedRsvpCount = count($checkedRsvpRows);
$walkInCount = count($walkInRows);

$metaParts = [htmlspecialchars($eventDate, ENT_QUOTES, 'UTF-8')];
if ($ksuTime !== '') {
	$metaParts[] = OutputEscape::termWithTooltipAndValue(
		Text::_('COM_WENDFOLD_EVENT_KSU'),
		Text::_('COM_WENDFOLD_EVENT_KSU_DESC'),
		$ksuTime
	);
}
if ($locName !== '') {
	$metaParts[] = htmlspecialchars($locName, ENT_QUOTES, 'UTF-8');
}
if ($cityState !== '' && $cityState !== $locName) {
	$metaParts[] = htmlspecialchars($cityState, ENT_QUOTES, 'UTF-8');
}
$metaLine = implode(' - ', $metaParts);

$getIssueCode = static function ($member): string {
	$hasWaiver = !empty($member->waiver_signed);
	$hasDues = !empty($member->paid_this_year) || !empty($member->dues_paid);

	if (!$hasWaiver) {
		return 'waiver';
	}

	if (!$hasDues) {
		return 'dues';
	}

	return '';
};

$issueTotal = 0;
$noWaiverCount = 0;
$lateDuesCount = 0;

foreach (array_merge($rsvpRows, $walkInRows) as $member) {
	$issueCode = $getIssueCode($member);

	if ($issueCode === 'waiver') {
		$issueTotal++;
		$noWaiverCount++;
	} elseif ($issueCode === 'dues') {
		$issueTotal++;
		$lateDuesCount++;
	}
}

$participantsByProfile = [];
foreach (array_merge($rsvpRows, $walkInRows) as $member) {
	$profileId = (int) ($member->profile_id ?? 0);

	if ($profileId > 0) {
		$participantsByProfile[$profileId] = $member;
	}
}

$assignedGroupCount = 0;
foreach ($participantsByProfile as $member) {
	if ((int) ($member->group_id ?? 0) > 0) {
		$assignedGroupCount++;
	}
}
$participantCount = count($participantsByProfile);
$unassignedCount = max(0, $participantCount - $assignedGroupCount);

$renderFlags = static function ($member): string {
	$hasWaiver = !empty($member->waiver_signed);
	$hasDues = !empty($member->paid_this_year) || !empty($member->dues_paid);
	$html = '';

	if (!$hasWaiver) {
		$html .= '<span class="hc-badge hc-badge-danger">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_WAIVER_WARNING'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	if ($hasWaiver && !$hasDues) {
		$html .= '<span class="hc-badge hc-badge-warning">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_DUES_REMINDER'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	return $html;
};

$renderPhoto = static function ($member, string $class, string $placeholderClass): string {
	$photoSrc = !empty($member->photo) ? Uri::root() . ltrim((string) $member->photo, '/') : '';

	if ($photoSrc !== '') {
		return '<img class="' . $class . '" src="' . htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8') . '" loading="lazy" decoding="async">';
	}

	return '<div class="' . $placeholderClass . '" aria-hidden="true">&#128100;</div>';
};

$renderRoleBadges = static function ($member, $event): string {
	$html = '';
	$profileId = (int) ($member->profile_id ?? 0);

	if ($profileId > 0 && $profileId === (int) ($event->road_captain_id ?? 0)) {
		$html .= '<span class="hc-badge hc-badge-neutral">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_ROLE_RC'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	if ($profileId > 0 && $profileId === (int) ($event->sweep_id ?? 0)) {
		$html .= '<span class="hc-badge hc-badge-neutral">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_ROLE_SWEEP'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	if ($profileId > 0 && $profileId === (int) ($member->group_road_captain_id ?? 0) && $profileId !== (int) ($event->road_captain_id ?? 0)) {
		$html .= '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_RC'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	if ($profileId > 0 && $profileId === (int) ($member->group_sweep_id ?? 0) && $profileId !== (int) ($event->sweep_id ?? 0)) {
		$html .= '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_ROLE_SWEEP'), ENT_QUOTES, 'UTF-8') . '</span>';
	}

	return $html;
};

$renderRoleOptionTags = static function (array $options, int $selectedId, string $noneText): string {
	$html = '<option value="0">' . htmlspecialchars($noneText, ENT_QUOTES, 'UTF-8') . '</option>';

	foreach ($options as $option) {
		$value = (int) ($option->value ?? 0);
		$selected = $value === $selectedId ? ' selected' : '';
		$html .= '<option value="' . $value . '"' . $selected . '>' . htmlspecialchars((string) ($option->text ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
	}

	return $html;
};

$renderGroupAssignment = static function ($member) use ($assignGroupAction, $event, $groups, $eventOpen, $showRideGroups): string {
	if (!$showRideGroups || empty($groups)) {
		return '';
	}

	$profileId = (int) ($member->profile_id ?? 0);

	if ($profileId <= 0) {
		return '';
	}

	$currentGroupId = (int) ($member->group_id ?? 0);
	$label = !empty($member->group_title)
		? (string) $member->group_title
		: Text::_('COM_WENDFOLD_CHECKIN_GROUP_UNASSIGNED');

	$meta = [];
	if (!empty($member->group_staging_hint)) {
		$meta[] = (string) $member->group_staging_hint;
	}
	if (!empty($member->group_order_hint)) {
		$meta[] = (string) $member->group_order_hint;
	}

	$roleLocked = $profileId > 0
		&& ($profileId === (int) ($member->group_road_captain_id ?? 0) || $profileId === (int) ($member->group_sweep_id ?? 0));

	$html = '<div class="hc-rc-group-assign">';
	$html .= '<div class="hc-rc-group-assign__summary">';
	$html .= '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_LABEL'), ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
	if (!empty($meta)) {
		$html .= '<span class="hc-rc-group-assign__meta">' . htmlspecialchars(implode(' - ', $meta), ENT_QUOTES, 'UTF-8') . '</span>';
	}
	$html .= '</div>';

	if ($eventOpen && !$roleLocked) {
		$html .= '<form method="post" action="' . htmlspecialchars($assignGroupAction, ENT_QUOTES, 'UTF-8') . '" class="hc-rc-group-assign__form">';
		$html .= '<input type="hidden" name="event_id" value="' . (int) $event->id . '">';
		$html .= '<input type="hidden" name="profile_id" value="' . $profileId . '">';
		$html .= '<select name="event_group_id" class="form-select form-select-sm hc-rc-group-select">';
		$html .= '<option value="0">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_UNASSIGNED'), ENT_QUOTES, 'UTF-8') . '</option>';
		foreach ($groups as $group) {
			$groupId = (int) ($group->id ?? 0);
			$selected = $groupId === $currentGroupId ? ' selected' : '';
			$html .= '<option value="' . $groupId . '"' . $selected . '>' . htmlspecialchars((string) ($group->title ?? ('Group ' . (int) ($group->group_order ?? 0))), ENT_QUOTES, 'UTF-8') . '</option>';
		}
		$html .= '</select>';
		$html .= HTMLHelper::_('form.token');
		$html .= '<button type="submit" class="' . hcButtonClasses('secondary', 'hc-btn-sm') . '">' . htmlspecialchars(Text::_('JSAVE'), ENT_QUOTES, 'UTF-8') . '</button>';
		$html .= '</form>';
	} elseif ($roleLocked) {
		$html .= '<div class="hc-rc-group-assign__meta">' . htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_ASSIGNMENT_ROLE_LOCKED'), ENT_QUOTES, 'UTF-8') . '</div>';
	}

	$html .= '</div>';

	return $html;
};

ob_start();
?>
<a class="<?php echo hcButtonClasses('back'); ?> hc-rc-back-btn" href="<?php echo htmlspecialchars($eventUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_CHECKIN_BACK_TO_DETAILS'); ?>
</a>
<?php
$headerActions = trim((string) ob_get_clean());

ob_start();
?>
<span class="hc-badge <?php echo htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8'); ?>
</span>
<?php if ($eventOpen) : ?>
	<span class="hc-badge hc-badge-social"><?php echo Text::_('COM_WENDFOLD_EVENT_WHOS_RIDING_LIVE'); ?></span>
<?php else : ?>
	<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ROSTER_VIEW_ONLY'); ?></span>
<?php endif; ?>
<?php if ($showRideGroups) : ?>
	<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_PANEL_TITLE'); ?></span>
<?php endif; ?>
<?php
$headerBadges = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_EVENT_MANAGE_CHECKIN'),
	'subtitle' => (string) $event->title,
	'metaItems' => [['html' => $metaLine]],
	'badgesHtml' => $headerBadges,
	'actionsHtml' => $headerActions,
];
?>

<div class="hc-page hc-page--rcattendance hc-rc-page"
	data-dialog-confirm-title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_DIALOG_CONFIRM_TITLE'), ENT_QUOTES, 'UTF-8'); ?>"
	data-dialog-alert-title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_DIALOG_ALERT_TITLE'), ENT_QUOTES, 'UTF-8'); ?>"
	data-dialog-confirm-label="<?php echo htmlspecialchars(Text::_('JOK'), ENT_QUOTES, 'UTF-8'); ?>"
	data-dialog-cancel-label="<?php echo htmlspecialchars(Text::_('JCANCEL'), ENT_QUOTES, 'UTF-8'); ?>"
	data-dialog-close-label="<?php echo htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8'); ?>"
>
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack hc-rc-layout">
		<?php if ($canManageStatus) : ?>
			<section class="hc-panel hc-rc-layout__controls">
				<div class="hc-panel__body">
					<div class="hc-rc-toolbar-inline">
						<div>
							<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_STATUS_CONTROLS'); ?></div>
							<div class="hc-muted"><?php echo Text::_('COM_WENDFOLD_RCATTENDANCE_SUMMARY_DESC'); ?></div>
						</div>

						<div class="hc-rc-status-buttons-inline">
							<?php foreach ($statusButtons as $statusValue => $statusButton) : ?>
								<?php $buttonClasses = hcButtonClasses($statusButton['button']); ?>
								<?php if ($currentStatus === $statusValue) : ?>
									<?php $buttonClasses .= ' is-active'; ?>
								<?php endif; ?>
								<form method="post" action="<?php echo htmlspecialchars($setStatusUrl, ENT_QUOTES, 'UTF-8'); ?>" class="hc-rc-status-form">
									<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
									<input type="hidden" name="status" value="<?php echo (int) $statusValue; ?>">
									<?php echo HTMLHelper::_('form.token'); ?>
									<button
										type="submit"
										class="<?php echo $buttonClasses; ?> hc-rc-status-button"
										<?php if ($currentStatus !== $statusValue) : ?>
											data-confirm="<?php echo htmlspecialchars(Text::sprintf('COM_WENDFOLD_CHECKIN_STATUS_CHANGE_CONFIRM', $statusButton['label']), ENT_QUOTES, 'UTF-8'); ?>"
										<?php endif; ?>
									>
										<?php echo htmlspecialchars($statusButton['label'], ENT_QUOTES, 'UTF-8'); ?>
									</button>
								</form>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section class="hc-panel hc-rc-layout__summary">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_RCATTENDANCE_SUMMARY_TITLE'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_CHECKIN_STATUS_HELP'); ?></div>
				</div>
			</div>
			<div class="hc-panel__body">
				<div class="hc-summary-stats-grid hc-rc-stats-grid hc-rc-stats-grid--top">
					<div class="hc-kpi-card hc-rc-sidebar-stat">
						<div class="hc-stat-value"><?php echo $rosterCount; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ROSTER'); ?></div>
					</div>
					<div class="hc-kpi-card hc-rc-sidebar-stat">
						<div class="hc-stat-value"><?php echo (int) $this->rsvpCount; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_RSVP_MEMBERS'); ?></div>
					</div>
					<div class="hc-kpi-card hc-rc-sidebar-stat">
						<div class="hc-stat-value"><?php echo $walkInCount; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_WALKIN_COUNT'); ?></div>
					</div>
					<div class="hc-kpi-card hc-rc-sidebar-stat">
						<div class="hc-stat-value"><?php echo $noWaiverCount; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_NO_WAIVER_COUNT'); ?></div>
					</div>
					<div class="hc-kpi-card hc-rc-sidebar-stat">
						<div class="hc-stat-value"><?php echo $lateDuesCount; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_LATE_DUES_COUNT'); ?></div>
					</div>
					<?php if ($showRideGroups) : ?>
						<div class="hc-kpi-card hc-rc-sidebar-stat">
							<div class="hc-stat-value"><?php echo count($groups); ?></div>
							<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_COUNT'); ?></div>
						</div>
						<div class="hc-kpi-card hc-rc-sidebar-stat">
							<div class="hc-stat-value"><?php echo $assignedGroupCount; ?></div>
							<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ASSIGNED_COUNT'); ?></div>
						</div>
						<div class="hc-kpi-card hc-rc-sidebar-stat">
							<div class="hc-stat-value"><?php echo $unassignedCount; ?></div>
							<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_UNASSIGNED_COUNT'); ?></div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<?php if ($showRideGroups) : ?>
			<section class="hc-panel hc-rc-layout__groups">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_PANEL_TITLE'); ?></h2>
						<div class="hc-panel__meta">
							<?php echo Text::sprintf('COM_WENDFOLD_CHECKIN_GROUPS_META', count($groups), $unassignedCount); ?>
						</div>
					</div>
				</div>

				<div class="hc-panel__body">
					<?php if (empty($groups)) : ?>
						<div class="hc-rc-groups-empty">
							<p class="hc-muted mb-3"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_EMPTY_HELP'); ?></p>

							<?php if ($eventOpen) : ?>
								<form method="post" action="<?php echo htmlspecialchars($createGroupsAction, ENT_QUOTES, 'UTF-8'); ?>" class="hc-rc-groups-create-form">
									<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
									<label class="hc-meta-label" for="hc-group-count"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_COUNT_LABEL'); ?></label>
									<div class="hc-rc-groups-create-form__controls">
										<select name="group_count" id="hc-group-count" class="form-select hc-form-width-sm">
											<?php for ($i = 2; $i <= 10; $i++) : ?>
												<option value="<?php echo $i; ?>"<?php echo $i === 2 ? ' selected' : ''; ?>><?php echo $i; ?></option>
											<?php endfor; ?>
										</select>
										<?php echo HTMLHelper::_('form.token'); ?>
										<button type="submit" class="<?php echo hcButtonClasses('action'); ?>">
											<?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_CREATE_BUTTON'); ?>
										</button>
									</div>
								</form>
							<?php else : ?>
								<p class="hc-empty mb-0"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_NONE_CREATED'); ?></p>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<?php if ($eventOpen) : ?>
							<p class="hc-muted"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_EDIT_HELP'); ?></p>

							<?php $removeGroupForms = ''; ?>
							<form method="post" action="<?php echo htmlspecialchars($saveGroupsAction, ENT_QUOTES, 'UTF-8'); ?>" class="hc-rc-group-editor-form">
								<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">

								<div class="hc-rc-groups-grid">
									<?php foreach ($groups as $group) : ?>
										<?php
										$groupId = (int) ($group->id ?? 0);
										$groupMemberCount = count($group->members);
										$removeGroupConfirm = Text::sprintf('COM_WENDFOLD_CHECKIN_GROUP_REMOVE_CONFIRM', (string) ($group->title ?? ('Group ' . (int) ($group->group_order ?? 0))), $groupMemberCount);
										$removeGroupFormId = 'hc-rc-remove-group-' . $groupId;
										?>
										<article class="hc-rc-group-card">
											<div class="hc-rc-group-card__head">
												<div>
													<h3 class="hc-rc-group-card__title"><?php echo htmlspecialchars((string) ($group->title ?? ('Group ' . (int) ($group->group_order ?? 0))), ENT_QUOTES, 'UTF-8'); ?></h3>
													<span class="hc-badge hc-badge-outline"><?php echo Text::sprintf($groupMemberCount === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', $groupMemberCount); ?></span>
												</div>
												<?php if (count($groups) > 1) : ?>
													<button
														type="submit"
														class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?>"
														form="<?php echo htmlspecialchars($removeGroupFormId, ENT_QUOTES, 'UTF-8'); ?>"
													>
														<?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_REMOVE_BUTTON'); ?>
													</button>
													<?php
													ob_start();
													?>
													<form
														id="<?php echo htmlspecialchars($removeGroupFormId, ENT_QUOTES, 'UTF-8'); ?>"
														method="post"
														action="<?php echo htmlspecialchars($removeGroupAction, ENT_QUOTES, 'UTF-8'); ?>"
														data-confirm="<?php echo htmlspecialchars($removeGroupConfirm, ENT_QUOTES, 'UTF-8'); ?>">
														<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
														<input type="hidden" name="group_id" value="<?php echo $groupId; ?>">
														<?php echo HTMLHelper::_('form.token'); ?>
													</form>
													<?php
													$removeGroupForms .= ob_get_clean();
													?>
												<?php endif; ?>
											</div>

											<div class="hc-rc-group-form-grid">
												<div>
													<label class="form-label" for="hc-group-title-<?php echo (int) $group->id; ?>"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_TITLE'); ?></label>
													<input type="text" class="form-control" id="hc-group-title-<?php echo (int) $group->id; ?>" name="groups[<?php echo (int) $group->id; ?>][title]" value="<?php echo htmlspecialchars((string) ($group->title ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
												</div>
												<div>
													<label class="form-label" for="hc-group-rc-<?php echo (int) $group->id; ?>"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_RC'); ?></label>
													<select class="form-select" id="hc-group-rc-<?php echo (int) $group->id; ?>" name="groups[<?php echo (int) $group->id; ?>][road_captain_id]">
														<?php echo $renderRoleOptionTags($groupRoleOptions['road_captains'], (int) ($group->road_captain_id ?? 0), Text::_('COM_WENDFOLD_FIELD_ROAD_CAPTAIN_NONE')); ?>
													</select>
												</div>
												<div>
													<label class="form-label" for="hc-group-sweep-<?php echo (int) $group->id; ?>"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_SWEEP'); ?></label>
													<select class="form-select" id="hc-group-sweep-<?php echo (int) $group->id; ?>" name="groups[<?php echo (int) $group->id; ?>][sweep_id]">
														<?php echo $renderRoleOptionTags($groupRoleOptions['sweeps'], (int) ($group->sweep_id ?? 0), Text::_('COM_WENDFOLD_FIELD_SWEEP_NONE')); ?>
													</select>
												</div>
												<div>
													<label class="form-label" for="hc-group-stage-<?php echo (int) $group->id; ?>"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_STAGING'); ?></label>
													<input type="text" class="form-control" id="hc-group-stage-<?php echo (int) $group->id; ?>" name="groups[<?php echo (int) $group->id; ?>][staging_hint]" value="<?php echo htmlspecialchars((string) ($group->staging_hint ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_STAGING_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>">
												</div>
												<div>
													<label class="form-label" for="hc-group-order-<?php echo (int) $group->id; ?>"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_ORDER'); ?></label>
													<input type="text" class="form-control" id="hc-group-order-<?php echo (int) $group->id; ?>" name="groups[<?php echo (int) $group->id; ?>][order_hint]" value="<?php echo htmlspecialchars((string) ($group->order_hint ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_ORDER_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>">
												</div>
											</div>

											<input type="hidden" name="groups[<?php echo (int) $group->id; ?>][group_order]" value="<?php echo (int) ($group->group_order ?? 0); ?>">

											<div class="hc-rc-group-members">
												<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ASSIGNED_MEMBERS'); ?></div>
												<?php if (!empty($group->members)) : ?>
													<div class="hc-rc-group-members__list">
														<?php foreach ($group->members as $assignedMember) : ?>
															<div class="hc-rc-group-members__item">
																<span class="hc-strong"><?php echo htmlspecialchars((string) ($assignedMember->member_name ?? '--'), ENT_QUOTES, 'UTF-8'); ?></span>
																<?php if (!empty($assignedMember->checked_in)) : ?>
																	<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_EVENT_RIDER_STATUS_CHECKED_IN'); ?></span>
																<?php endif; ?>
															</div>
														<?php endforeach; ?>
													</div>
												<?php else : ?>
													<div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_ASSIGNED_MEMBERS_EMPTY'); ?></div>
												<?php endif; ?>
											</div>
										</article>
									<?php endforeach; ?>
								</div>

								<div class="hc-page__actions hc-page__actions--footer">
									<?php echo HTMLHelper::_('form.token'); ?>
									<button type="submit" class="<?php echo hcButtonClasses('action'); ?>">
										<?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_SAVE_BUTTON'); ?>
									</button>
								</div>
							</form>
							<?php echo $removeGroupForms; ?>

							<form method="post" action="<?php echo htmlspecialchars($addGroupAction, ENT_QUOTES, 'UTF-8'); ?>" class="hc-rc-group-add-form">
								<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
								<?php echo HTMLHelper::_('form.token'); ?>
								<button type="submit" class="<?php echo hcButtonClasses('secondary'); ?>">
									<?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUPS_ADD_BUTTON'); ?>
								</button>
							</form>
						<?php else : ?>
							<div class="hc-rc-groups-grid">
								<?php foreach ($groups as $group) : ?>
									<article class="hc-rc-group-card is-readonly">
										<div class="hc-rc-group-card__head">
											<h3 class="hc-rc-group-card__title"><?php echo htmlspecialchars((string) ($group->title ?? ('Group ' . (int) ($group->group_order ?? 0))), ENT_QUOTES, 'UTF-8'); ?></h3>
											<span class="hc-badge hc-badge-outline"><?php echo Text::sprintf(count($group->members) === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', count($group->members)); ?></span>
										</div>
										<div class="hc-rc-group-readonly-meta">
											<?php if (!empty($group->road_captain_name)) : ?>
												<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_RC'); ?></span> <?php echo htmlspecialchars((string) $group->road_captain_name, ENT_QUOTES, 'UTF-8'); ?></div>
											<?php endif; ?>
											<?php if (!empty($group->sweep_name)) : ?>
												<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_SWEEP'); ?></span> <?php echo htmlspecialchars((string) $group->sweep_name, ENT_QUOTES, 'UTF-8'); ?></div>
											<?php endif; ?>
											<?php if (!empty($group->staging_hint)) : ?>
												<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_STAGING'); ?></span> <?php echo htmlspecialchars((string) $group->staging_hint, ENT_QUOTES, 'UTF-8'); ?></div>
											<?php endif; ?>
											<?php if (!empty($group->order_hint)) : ?>
												<div><span class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_GROUP_FIELD_ORDER'); ?></span> <?php echo htmlspecialchars((string) $group->order_hint, ENT_QUOTES, 'UTF-8'); ?></div>
											<?php endif; ?>
										</div>
									</article>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="hc-panel hc-rc-layout__rsvp">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_RSVP_MEMBERS'); ?></h2>
					<div class="hc-panel__meta">
						<?php echo Text::sprintf($rsvpTotal === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', $rsvpTotal); ?>
					</div>
				</div>

				<?php if ($eventOpen && !empty($otherMembers)) : ?>
					<div class="hc-panel__actions">
						<button type="button" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>" data-hc-open-panel>
							<?php echo Text::_('COM_WENDFOLD_CHECKIN_ADD_WALKIN'); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<div class="hc-panel__body">
				<?php if (empty($rsvpRows)) : ?>
					<p class="hc-empty mb-0"><?php echo Text::_('COM_WENDFOLD_CHECKIN_RSVP_EMPTY'); ?></p>
				<?php else : ?>
					<div class="hc-rc-list">
						<?php foreach ($rsvpRows as $member) : ?>
							<?php
							$issueCode = $getIssueCode($member);
							$profileId = (int) ($member->profile_id ?? 0);
							$isCheckedIn = !empty($member->attendance_id) || !empty($member->checked_in);
							$isAssignedRole = !empty($member->is_assigned_role);
							$timeLabel = !empty($member->check_in_time) ? HTMLHelper::_('date', (string) $member->check_in_time, 'g:i A') : '';
							?>
							<article class="hc-rc-card<?php echo $isCheckedIn ? '' : ' is-clear'; ?>">
								<div class="hc-rc-member-row">
									<?php echo $renderPhoto($member, 'hc-rc-avatar', 'hc-rc-avatar-ph'); ?>
									<div class="hc-rc-member-main">
										<div class="hc-rc-member-mainline">
											<div class="hc-rc-member-name"><?php echo htmlspecialchars((string) ($member->member_name ?? '--'), ENT_QUOTES, 'UTF-8'); ?></div>
											<div class="hc-rc-member-meta">
												<?php if ($isCheckedIn && $timeLabel !== '') : ?>
													<?php echo Text::_('COM_WENDFOLD_EVENT_RIDER_STATUS_CHECKED_IN'); ?> - <?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?>
												<?php else : ?>
													<?php echo Text::_('COM_WENDFOLD_CHECKIN_ACTION_REQUIRED'); ?>
												<?php endif; ?>
											</div>
										</div>
										<div class="hc-rc-flag-list">
											<?php echo $renderRoleBadges($member, $event); ?>
											<?php if ($isCheckedIn) : ?>
												<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ACTION_DONE'); ?></span>
											<?php else : ?>
												<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ACTION_REQUIRED'); ?></span>
											<?php endif; ?>
											<?php echo $renderFlags($member); ?>
										</div>
										<?php echo $renderGroupAssignment($member); ?>
									</div>
									<?php if ($eventOpen) : ?>
										<div class="hc-rc-member-actions">
											<?php if ($isCheckedIn) : ?>
												<form method="post" action="<?php echo htmlspecialchars($removeAction, ENT_QUOTES, 'UTF-8'); ?>"
													class="m-0">
													<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
													<input type="hidden" name="id" value="<?php echo (int) ($member->attendance_id ?? 0); ?>">
													<?php echo HTMLHelper::_('form.token'); ?>
													<button type="submit"
														class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?> hc-rc-action-btn">
														<?php echo Text::_('COM_WENDFOLD_CHECKIN_BUTTON_UNDO'); ?>
													</button>
												</form>
											<?php elseif ($issueCode === 'waiver') : ?>
												<span class="<?php echo hcButtonClasses('danger', 'hc-btn-sm disabled'); ?> hc-rc-action-btn" aria-disabled="true">
													<?php echo Text::_('COM_WENDFOLD_CHECKIN_WAIVER_WARNING'); ?>
												</span>
											<?php else : ?>
												<form method="post" action="<?php echo htmlspecialchars($addAction, ENT_QUOTES, 'UTF-8'); ?>" class="m-0">
													<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
													<input type="hidden" name="profile_id" value="<?php echo $profileId; ?>">
													<?php echo HTMLHelper::_('form.token'); ?>
													<button type="submit" class="<?php echo hcButtonClasses('action', 'hc-btn-sm'); ?> hc-rc-action-btn">
														<?php echo Text::_('COM_WENDFOLD_CHECKIN_BUTTON_CHECK_MEMBER_IN'); ?>
													</button>
												</form>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if (!empty($walkInRows)) : ?>
			<section class="hc-panel hc-rc-layout__walkins">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_WALKIN_MEMBERS'); ?></h2>
						<div class="hc-panel__meta"><?php echo Text::sprintf($walkInCount === 1 ? 'COM_WENDFOLD_RSVP_COUNT_SINGLE' : 'COM_WENDFOLD_RSVP_COUNT_PLURAL', $walkInCount); ?></div>
					</div>
				</div>

				<div class="hc-panel__body">
					<div class="hc-rc-list">
						<?php foreach ($walkInRows as $member) : ?>
							<?php
							$timeLabel = !empty($member->check_in_time) ? HTMLHelper::_('date', (string) $member->check_in_time, 'g:i A') : '';
							?>
							<article class="hc-rc-card is-clear">
								<div class="hc-rc-member-row">
									<?php echo $renderPhoto($member, 'hc-rc-avatar', 'hc-rc-avatar-ph'); ?>
									<div class="hc-rc-member-main">
										<div class="hc-rc-member-mainline">
											<div class="hc-rc-member-name"><?php echo htmlspecialchars((string) ($member->member_name ?? '--'), ENT_QUOTES, 'UTF-8'); ?></div>
											<?php if ($timeLabel !== '') : ?>
												<div class="hc-rc-member-meta"><?php echo Text::_('COM_WENDFOLD_EVENT_RIDER_STATUS_CHECKED_IN'); ?> - <?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
											<?php endif; ?>
										</div>
										<div class="hc-rc-flag-list">
											<span class="hc-badge hc-badge-neutral"><?php echo Text::_('COM_WENDFOLD_CHECKIN_WALKIN_MEMBERS'); ?></span>
											<?php echo $renderFlags($member); ?>
										</div>
										<?php echo $renderGroupAssignment($member); ?>
									</div>
									<?php if ($eventOpen) : ?>
										<div class="hc-rc-member-actions">
											<form method="post" action="<?php echo htmlspecialchars($removeAction, ENT_QUOTES, 'UTF-8'); ?>"
												class="m-0">
												<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
												<input type="hidden" name="id" value="<?php echo (int) ($member->attendance_id ?? 0); ?>">
												<?php echo HTMLHelper::_('form.token'); ?>
												<button type="submit" class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?> hc-rc-action-btn">
													<?php echo Text::_('COM_WENDFOLD_CHECKIN_BUTTON_UNDO'); ?>
												</button>
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

<?php if ($eventOpen) : ?>
	<div class="hc-rc-dock">
		<div class="hc-rc-dock-inner">
			<?php if ($rosterCount > 0) : ?>
				<form method="post"
					action="<?php echo htmlspecialchars($completeAction, ENT_QUOTES, 'UTF-8'); ?>"
					class="m-0"
					data-confirm="<?php echo $completeConfirmAttr; ?>">
					<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
					<?php echo HTMLHelper::_('form.token'); ?>
					<button type="submit" class="<?php echo hcButtonClasses('action'); ?>">
						<?php echo Text::_('COM_WENDFOLD_CHECKIN_COMPLETE_EVENT'); ?> (<?php echo $rosterCount; ?>)
					</button>
				</form>
			<?php else : ?>
				<span class="<?php echo hcButtonClasses('action', 'disabled'); ?>" aria-disabled="true">
					<?php echo Text::_('COM_WENDFOLD_CHECKIN_COMPLETE_EVENT'); ?> (0)
				</span>
			<?php endif; ?>

			<form method="post"
				action="<?php echo htmlspecialchars($cancelAction, ENT_QUOTES, 'UTF-8'); ?>"
				class="m-0"
				data-confirm="<?php echo $cancelConfirmAttr; ?>">
				<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
				<?php echo HTMLHelper::_('form.token'); ?>
				<button type="submit" class="<?php echo hcButtonClasses('danger'); ?>">
					<?php echo Text::_('COM_WENDFOLD_CHECKIN_CANCEL_EVENT'); ?>
				</button>
			</form>
		</div>
	</div>

	<?php if (!empty($otherMembers)) : ?>
		<div class="hc-rc-panel-backdrop" id="addPanel" hidden data-hc-panel-backdrop>
			<div class="hc-rc-panel" role="dialog" aria-modal="true" aria-labelledby="hc-rc-add-title">
				<div class="hc-rc-panel-head">
					<div>
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ADD_WALKIN'); ?></div>
						<h2 id="hc-rc-add-title" class="h5 mb-0"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ADD_WALKIN'); ?></h2>
					</div>
					<button type="button" class="hc-rc-panel-close" data-hc-close-panel aria-label="<?php echo Text::_('JCLOSE'); ?>">&#x2715;</button>
				</div>

				<div class="p-3 border-bottom">
					<input
						type="text"
						class="form-control"
						id="memberSearch"
						placeholder="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_CHECKIN_SEARCH_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"
						autocomplete="off"
						spellcheck="false"
						data-hc-search
					>
				</div>

				<div class="hc-rc-panel-body">
					<?php foreach ($otherMembers as $member) : ?>
						<?php
						$searchVal = function_exists('mb_strtolower') ? mb_strtolower((string) $member->member_name, 'UTF-8') : strtolower((string) $member->member_name);
						$issueCode = $getIssueCode($member);
						?>
						<div
							class="hc-rc-pick-row"
							data-member-row
							data-name="<?php echo htmlspecialchars($searchVal, ENT_QUOTES, 'UTF-8'); ?>"
						>
							<?php echo $renderPhoto($member, 'hc-rc-pick-avatar', 'hc-rc-pick-avatar-ph'); ?>

							<div class="hc-rc-pick-main">
								<div class="fw-semibold text-break"><?php echo htmlspecialchars((string) $member->member_name, ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="hc-rc-flag-list mt-2"><?php echo $renderFlags($member); ?></div>
							</div>

							<div class="hc-rc-pick-action-wrap">
								<?php if ($issueCode === 'waiver') : ?>
									<span class="<?php echo hcButtonClasses('danger', 'hc-btn-sm disabled'); ?> hc-rc-pick-action" aria-disabled="true">
										<?php echo Text::_('COM_WENDFOLD_CHECKIN_WAIVER_WARNING'); ?>
									</span>
								<?php else : ?>
									<form method="post" action="<?php echo htmlspecialchars($addAction, ENT_QUOTES, 'UTF-8'); ?>" class="m-0">
										<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
										<input type="hidden" name="profile_id" value="<?php echo (int) $member->profile_id; ?>">
										<?php echo HTMLHelper::_('form.token'); ?>
										<button type="submit" class="<?php echo hcButtonClasses('action', 'hc-btn-sm'); ?> hc-rc-pick-action">
											<?php echo Text::_('COM_WENDFOLD_CHECKIN_BUTTON_CHECK_MEMBER_IN'); ?>
										</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="hc-rc-empty py-4" id="searchNoResults" hidden><?php echo Text::_('COM_WENDFOLD_CHECKIN_SEARCH_NO_RESULTS'); ?></div>
				</div>
			</div>
		</div>
	<?php endif; ?>
<?php endif; ?>

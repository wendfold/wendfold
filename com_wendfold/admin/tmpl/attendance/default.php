<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Administrator\Helper\AttendanceRosterStatus;
use Wendfold\Component\Wendfold\Administrator\Helper\QrCodeHelper;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// Ensure Bootstrap modal JavaScript is loaded
HTMLHelper::_('bootstrap.modal');

$event       = $this->event;
$methods     = [
	1 => Text::_('COM_WENDFOLD_ATTENDANCE_METHOD_QR'),
	2 => Text::_('COM_WENDFOLD_ATTENDANCE_METHOD_MANUAL'),
	3 => Text::_('COM_WENDFOLD_ATTENDANCE_METHOD_SELF'),
];
$qrImage     = '';
$qrExtension = 'png';
$qrMarkup    = $checkinUrlEscaped = '';

if ($event) {
	$checkinUrl = RouteHelper::site(['view' => 'checkin', 'token' => (string) $event->qr_token], true);
	$checkinUrlEscaped = htmlspecialchars((string) $checkinUrl, ENT_QUOTES, 'UTF-8');

	try
	{
		$qrImage = QrCodeHelper::dataUri($checkinUrl);
		$qrExtension = QrCodeHelper::fileExtension();
	}
	catch (\Throwable)
	{
		$qrImage = '';
	}

	if ($qrImage !== '')
	{
		$qrAlt = htmlspecialchars(Text::_('COM_WENDFOLD_QR_CODE_ALT'), ENT_QUOTES, 'UTF-8');
		$qrSrc = htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8');
		$qrFile = 'event-' . (int) $event->id . '-checkin-qr.' . $qrExtension;
		$qrMarkup = '<img src="' . $qrSrc . '" alt="' . $qrAlt . '"'
			. ' style="width:200px;height:200px;border:1px solid #ddd;border-radius:8px;padding:6px;">'
			. '<div class="mt-3"><a href="' . $qrSrc . '" download="' . $qrFile . '"'
			. ' class="btn btn-outline-primary btn-sm">'
			. '<span class="icon-download me-1" aria-hidden="true"></span>'
			. Text::_('COM_WENDFOLD_DOWNLOAD_QR')
			. '</a></div>';
	}
	else
	{
		$qrMarkup = '<p class="text-muted mb-0">' . Text::_('COM_WENDFOLD_QR_UNAVAILABLE') . '</p>';
	}
}

$attendanceCompleteAction = htmlspecialchars(Route::_('index.php?option=com_wendfold&task=attendance.complete', false), ENT_QUOTES, 'UTF-8');
$attendanceCancelAction = htmlspecialchars(Route::_('index.php?option=com_wendfold&task=attendance.cancel', false), ENT_QUOTES, 'UTF-8');
$attendanceRemoveAction = htmlspecialchars(Route::_('index.php?option=com_wendfold&task=attendance.remove', false), ENT_QUOTES, 'UTF-8');
$completeEventConfirm = htmlspecialchars(Text::sprintf('COM_WENDFOLD_CONFIRM_COMPLETE_EVENT', count($this->items)), ENT_QUOTES, 'UTF-8');
$cancelEventConfirm = htmlspecialchars(Text::_('COM_WENDFOLD_CONFIRM_CANCEL_EVENT'), ENT_QUOTES, 'UTF-8');
$attendanceRemoveForms = '';
$removeConfirmAttr = [];

if (!empty($this->items))
{
	foreach ($this->items as $item)
	{
		$removeConfirmAttr[(int) $item->id] = OutputEscape::confirm(
			Text::_('COM_WENDFOLD_ATTENDANCE_REMOVE_CONFIRM'),
			(string) ($item->member_name ?? '')
		);

		if ($event)
		{
			$attendanceRemoveForms .= '<form id="attendance-remove-' . (int) $item->id . '" action="'
				. $attendanceRemoveAction
				. '" method="post">'
				. '<input type="hidden" name="id" value="' . (int) $item->id . '">'
				. '<input type="hidden" name="event_id" value="' . (int) $event->id . '">'
				. HTMLHelper::_('form.token')
				. '</form>';
		}
	}
}
?>

<div class="row">

	<!-- Attendance List -->
	<div class="col-lg-8">

		<?php if ($event) : ?>
		<div class="card mb-3">
			<div class="card-body p-2 d-flex align-items-center gap-3">
				<span class="icon-calendar-alt fs-4 text-muted"></span>
				<div>
					<strong><?php echo htmlspecialchars((string) $event->title, ENT_QUOTES, 'UTF-8'); ?></strong><br>
					<span class="small text-muted">
						<?php echo DateOnly::format($event->event_date, 'D, M j, Y'); ?>
						<?php if ($event->ksu_time) : ?>
							&bull; <?php echo OutputEscape::termWithTooltipAndValue(
								Text::_('COM_WENDFOLD_EVENT_KSU'),
								Text::_('COM_WENDFOLD_EVENT_KSU_DESC'),
								date('g:i A', strtotime($event->ksu_time))
							); ?>
						<?php endif; ?>
					</span>
				</div>
				<div class="ms-auto d-flex gap-2">
					<?php
					$rsvpCount = (int) ($this->rsvpCount ?? 0);
					if ($rsvpCount > 0) : ?>
						<span class="badge bg-info"><?php echo $rsvpCount; ?> <?php echo Text::_('COM_WENDFOLD_LABEL_RSVPS'); ?></span>
					<?php endif; ?>
					<span class="badge bg-primary fs-6"><?php echo count($this->items); ?> <?php echo Text::_('COM_WENDFOLD_LABEL_CHECKED_IN'); ?></span>
				</div>
			</div>
		</div>

		<!-- Action Buttons -->
		<div class="mb-3 d-flex gap-2">
			<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
				<span class="icon-plus me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_BTN_ADD_MEMBER_ROSTER'); ?>
			</button>

			<?php if (count($this->items) > 0) : ?>
			<form action="<?php echo $attendanceCompleteAction; ?>"
				method="post"
				class="d-inline">
				<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
				<?php echo HTMLHelper::_('form.token'); ?>
				<button type="submit"
						class="btn btn-primary btn-sm"
						data-confirm="<?php echo $completeEventConfirm; ?>">
					<span class="icon-checkmark me-1" aria-hidden="true"></span>
					<?php echo Text::_('COM_WENDFOLD_BTN_COMPLETE_EVENT'); ?>
					(<?php echo count($this->items); ?>
					<?php echo Text::_('COM_WENDFOLD_UNIT_RIDERS'); ?>)
				</button>
			</form>

			<form action="<?php echo $attendanceCancelAction; ?>"
				method="post"
				class="d-inline">
				<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
				<?php echo HTMLHelper::_('form.token'); ?>
				<button type="submit"
						class="btn btn-warning btn-sm"
						data-confirm="<?php echo $cancelEventConfirm; ?>">
					<span class="icon-times me-1" aria-hidden="true"></span>
					<?php echo Text::_('COM_WENDFOLD_BTN_CANCEL_EVENT'); ?>
				</button>
			</form>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<form action="<?php echo Route::_('index.php?option=com_wendfold&view=attendance'); ?>"
			  method="post" name="adminForm" id="adminForm">

			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th style="width:50px"></th>
						<th style="width:40px"></th>
						<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER'); ?></th>
						<th style="width:100px"><?php echo Text::_('COM_WENDFOLD_COL_MEMBER_NUMBER'); ?></th>
						<th style="width:130px"><?php echo Text::_('COM_WENDFOLD_COL_CHECKIN_TIME'); ?></th>
						<th style="width:90px"><?php echo Text::_('COM_WENDFOLD_COL_METHOD'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($this->items)) : ?>
					<tr>
						<td colspan="7" class="text-center p-4 text-muted">
							<?php echo Text::_('COM_WENDFOLD_NO_CHECKINS'); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ($this->items as $i => $item) : ?>
					<tr>
						<td class="text-center">
							<button type="submit"
									class="btn btn-sm btn-danger"
									form="attendance-remove-<?php echo (int) $item->id; ?>"
									data-confirm="<?php echo $removeConfirmAttr[(int) $item->id]; ?>"
									title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_TIP_REMOVE_FROM_ROSTER'), ENT_QUOTES, 'UTF-8'); ?>">
								<span class="icon-times" aria-hidden="true"></span>
							</button>
						</td>
						<td>
							<?php
							$statusBadge = AttendanceRosterStatus::badgeCode(
								$item->waiver_signed ?? null,
								$item->dues_paid ?? null
							);

							if ($statusBadge === 'waiver') {
								echo '<span class="badge bg-danger me-1" title="'
									. htmlspecialchars(Text::_('COM_WENDFOLD_TIP_NO_WAIVER'), ENT_QUOTES, 'UTF-8')
									. '">'
									. htmlspecialchars(Text::_('COM_WENDFOLD_BADGE_NO_WAIVER'), ENT_QUOTES, 'UTF-8')
									. '</span>';
							} elseif ($statusBadge === 'dues') {
								echo '<span class="badge bg-warning text-dark me-1" title="'
									. htmlspecialchars(Text::_('COM_WENDFOLD_TIP_DUES_UNPAID'), ENT_QUOTES, 'UTF-8')
									. '">'
									. htmlspecialchars(Text::_('COM_WENDFOLD_BADGE_DUES_UNPAID'), ENT_QUOTES, 'UTF-8')
									. '</span>';
							}
							?>
						</td>
						<td>
							<?php if (!empty($item->photo)) : ?>
								<img src="<?php echo htmlspecialchars(Uri::root() . $item->photo, ENT_QUOTES, 'UTF-8'); ?>"
									 style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
							<?php else : ?>
								<span class="icon-user-circle fs-4 text-muted"></span>
							<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars($item->member_name ?? ' - ', ENT_QUOTES); ?></td>
						<td><?php echo htmlspecialchars($item->member_number ?? ' - ', ENT_QUOTES); ?></td>
						<td class="small"><?php echo HTMLHelper::_('date', $item->check_in_time, 'M j g:i A'); ?></td>
						<td>
							<?php
							$method = $methods[$item->check_in_method] ?? Text::_('COM_WENDFOLD_ATTENDANCE_METHOD_UNKNOWN');
							$cls    = match ((int) $item->check_in_method) {
								1 => 'bg-success',
								2 => 'bg-warning text-dark',
								3 => 'bg-info text-dark',
								default => 'bg-secondary'
							};
	?>
							<span class="badge <?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></span>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<input type="hidden" name="task" value="">
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>

		<?php echo $attendanceRemoveForms; ?>
	</div>

	<!-- QR Code Sidebar -->
	<?php if ($event) : ?>
	<div class="col-lg-4">
		<div class="card">
			<div class="card-header bg-primary text-white fw-bold">
				<span class="icon-qrcode me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_QR_CODE'); ?>
			</div>
			<div class="card-body text-center">
				<?php echo $qrMarkup; ?>
				<div class="mt-2 small text-muted">
					<?php echo Text::_('COM_WENDFOLD_QR_CHECKIN_NOTE'); ?>
				</div>
				<hr>
				<div class="small text-muted text-break" style="font-size:.7rem;"><?php echo $checkinUrlEscaped; ?></div>
			</div>
		</div>
	</div>
	<?php endif; ?>

</div>

<!-- Add Member Modal -->
<?php if ($event) : ?>
<div class="modal fade" id="addMemberModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<form action="<?php echo Route::_('index.php?option=com_wendfold&task=attendance.add'); ?>" method="post">
				<div class="modal-header">
					<h5 class="modal-title"><?php echo Text::_('COM_WENDFOLD_BTN_ADD_MEMBER_ROSTER'); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label"><?php echo Text::_('COM_WENDFOLD_LABEL_SELECT_MEMBER'); ?></label>
						<select name="profile_id" class="form-select" required>
							<option value=""><?php echo Text::_('COM_WENDFOLD_OPTION_CHOOSE_MEMBER'); ?></option>
							<?php foreach ($this->availableMembers as $member) : ?>
								<option value="<?php echo (int) $member->id; ?>">
									<?php echo htmlspecialchars((string) ($member->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="modal-footer">
					<input type="hidden" name="event_id" value="<?php echo htmlspecialchars((int) $event->id, ENT_QUOTES, 'UTF-8'); ?>">
					<?php echo HTMLHelper::_('form.token'); ?>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
					<button type="submit" class="btn btn-primary"><?php echo Text::_('COM_WENDFOLD_BTN_ADD_TO_ROSTER'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

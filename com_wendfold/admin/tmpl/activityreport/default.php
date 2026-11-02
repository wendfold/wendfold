<?php
/**
 * Wendfold - Chapter Activity Report Template
 *
 * Renders the full activity report with date-range filter form,
 * summary cards, and detailed data tables.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.framework');

/** @var \Wendfold\Component\Wendfold\Administrator\View\Activityreport\HtmlView $this */

$exportUrl = Route::_(
	'index.php?option=com_wendfold&task=activityreport.exportcsv'
	. '&date_from=' . urlencode($this->filters->date_from)
	. '&date_to='   . urlencode($this->filters->date_to)
	. '&' . Session::getFormToken() . '=1',
	false
);
?>

<div class="container-fluid p-3">

	<!-- Page heading -->
	<div class="row mb-3">
		<div class="col-12">
			<h2 class="mb-1">
				<span class="icon-chart-bar me-2" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_TITLE'); ?>
			</h2>
			<p class="text-muted mb-0">
				<?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_DESC'); ?>
			</p>
		</div>
	</div>

	<!-- Date Range Filter Form -->
	<div class="card mb-4">
		<div class="card-header bg-light">
			<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_FILTER_HEADING'); ?></strong>
		</div>
		<div class="card-body">
			<form method="get" action="<?php echo Route::_('index.php?option=com_wendfold&view=activityreport'); ?>" class="row g-2 align-items-end">
				<input type="hidden" name="option" value="com_wendfold">
				<input type="hidden" name="view" value="activityreport">

				<div class="col-auto">
					<label for="date_from" class="form-label mb-1">
						<?php echo Text::_('COM_WENDFOLD_FIELD_DATE_FROM_LABEL'); ?>
					</label>
					<input type="date"
						   id="date_from"
						   name="date_from"
						   class="form-control form-control-sm"
						   value="<?php echo htmlspecialchars($this->filters->date_from, ENT_QUOTES); ?>">
				</div>

				<div class="col-auto">
					<label for="date_to" class="form-label mb-1">
						<?php echo Text::_('COM_WENDFOLD_FIELD_DATE_TO_LABEL'); ?>
					</label>
					<input type="date"
						   id="date_to"
						   name="date_to"
						   class="form-control form-control-sm"
						   value="<?php echo htmlspecialchars($this->filters->date_to, ENT_QUOTES); ?>">
				</div>

				<div class="col-auto">
					<button type="submit" class="btn btn-primary btn-sm">
						<?php echo Text::_('COM_WENDFOLD_BTN_RUN_REPORT'); ?>
					</button>
				</div>

				<div class="col-auto">
					<a href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-success btn-sm">
						<span class="icon-download me-1" aria-hidden="true"></span>
						<?php echo Text::_('COM_WENDFOLD_BTN_EXPORT_CSV'); ?>
					</a>
				</div>
			</form>
		</div>
		<div class="card-footer text-muted small">
			<?php echo Text::sprintf('COM_WENDFOLD_ACTIVITYREPORT_RANGE_LABEL', $this->filters->date_from, $this->filters->date_to); ?>
		</div>
	</div>

	<!-- ========================================================
		 SUMMARY CARDS
		 ======================================================== -->
	<div class="row g-3 mb-4">

		<!-- Total Events card -->
		<div class="col-6 col-md-3">
			<div class="card text-center h-100 border-warning">
				<div class="card-body py-3">
					<div class="display-6 fw-bold text-warning"><?php echo (int) $this->totalEvents; ?></div>
					<div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_TOTAL_EVENTS'); ?></div>
				</div>
			</div>
		</div>

		<!-- Total Attendance card -->
		<div class="col-6 col-md-3">
			<div class="card text-center h-100 border-primary">
				<div class="card-body py-3">
					<div class="display-6 fw-bold text-primary"><?php echo (int) $this->totalAttendance; ?></div>
					<div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_TOTAL_ATTENDANCE'); ?></div>
				</div>
			</div>
		</div>

		<!-- Dues Collected card -->
		<div class="col-6 col-md-3">
			<div class="card text-center h-100 border-success">
				<div class="card-body py-3">
					<div class="display-6 fw-bold text-success">$<?php echo number_format((float) $this->duesSummary->total_collected, 2); ?></div>
					<div class="small text-muted">
						<?php echo Text::sprintf('COM_WENDFOLD_ACTIVITYREPORT_DUES_COLLECTED', $this->duesSummary->members_paid); ?>
					</div>
				</div>
			</div>
		</div>

		<!-- New Members card -->
		<div class="col-6 col-md-3">
			<div class="card text-center h-100 border-info">
				<div class="card-body py-3">
					<div class="display-6 fw-bold text-info"><?php echo count($this->newMembers); ?></div>
					<div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_NEW_MEMBERS'); ?></div>
				</div>
			</div>
		</div>

	</div>

	<!-- ========================================================
		 EVENTS BY TYPE BREAKDOWN
		 ======================================================== -->
	<div class="row g-3 mb-4">
		<div class="col-12 col-md-6">
			<div class="card h-100">
				<div class="card-header">
					<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_EVENTS_BY_TYPE'); ?></strong>
				</div>
				<div class="card-body p-0">
					<table class="table table-sm mb-0">
						<thead class="table-light">
							<tr>
								<th><?php echo Text::_('COM_WENDFOLD_COL_TYPE'); ?></th>
								<th class="text-end"><?php echo Text::_('COM_WENDFOLD_COL_COUNT'); ?></th>
								<th><?php echo Text::_('COM_WENDFOLD_COL_BAR'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($this->eventsByType as $bucket) :
								$count = (int) ($bucket['count'] ?? 0);
								$pct   = $this->totalEvents > 0 ? round(($count / $this->totalEvents) * 100) : 0;
								$typeLabel = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($bucket['title'] ?? ''));
								$typeClass = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::adminBadgeClass((int) ($bucket['behavior'] ?? 0));
							?>
							<tr>
								<td>
									<span class="badge <?php echo htmlspecialchars($typeClass, ENT_QUOTES, 'UTF-8'); ?> me-1">
										<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
									</span>
								</td>
								<td class="text-end fw-bold"><?php echo $count; ?></td>
								<td style="width:40%">
									<?php if ($count > 0) : ?>
									<div class="progress" style="height:14px">
										<div class="progress-bar <?php echo htmlspecialchars($typeClass, ENT_QUOTES, 'UTF-8'); ?>"
											 role="progressbar"
											 style="width:<?php echo $pct; ?>%"
											 aria-valuenow="<?php echo $pct; ?>"
											 aria-valuemin="0"
											 aria-valuemax="100">
										</div>
									</div>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot class="table-light">
							<tr>
								<td><strong><?php echo Text::_('COM_WENDFOLD_COL_TOTAL'); ?></strong></td>
								<td class="text-end fw-bold"><?php echo (int) $this->totalEvents; ?></td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>

		<!-- Dues detail panel -->
		<div class="col-12 col-md-6">
			<div class="card h-100">
				<div class="card-header">
					<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_DUES_HEADING'); ?></strong>
				</div>
				<div class="card-body">
					<dl class="row mb-0">
						<dt class="col-sm-7"><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_DUES_MEMBERS_PAID'); ?></dt>
						<dd class="col-sm-5 fw-bold"><?php echo $this->duesSummary->members_paid; ?></dd>

						<dt class="col-sm-7"><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_DUES_TOTAL'); ?></dt>
						<dd class="col-sm-5 fw-bold">$<?php echo number_format((float) $this->duesSummary->total_collected, 2); ?></dd>
					</dl>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================================
		 TOP 10 TABLES (side by side on wider screens)
		 ======================================================== -->
	<div class="row g-3 mb-4">

		<!-- Top Attendees -->
		<div class="col-12 col-lg-6">
			<div class="card">
				<div class="card-header">
					<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_TOP_ATTENDEES'); ?></strong>
				</div>
				<?php if (empty($this->topAttendees)) : ?>
				<div class="card-body text-muted">
					<?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_NO_DATA'); ?>
				</div>
				<?php else : ?>
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead class="table-light">
							<tr>
								<th class="text-center" style="width:40px">#</th>
								<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER'); ?></th>
								<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER_NUMBER'); ?></th>
								<th class="text-end"><?php echo Text::_('COM_WENDFOLD_COL_EVENTS'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($this->topAttendees as $rank => $row) : ?>
							<tr>
								<td class="text-center text-muted"><?php echo $rank + 1; ?></td>
								<td><?php echo htmlspecialchars($row->member_name, ENT_QUOTES); ?></td>
								<td><small class="text-muted"><?php echo htmlspecialchars($row->member_number, ENT_QUOTES); ?></small></td>
								<td class="text-end fw-bold"><?php echo (int) $row->attendance_count; ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Top Riders by Miles -->
		<div class="col-12 col-lg-6">
			<div class="card">
				<div class="card-header">
					<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_TOP_RIDERS'); ?></strong>
				</div>
				<?php if (empty($this->topRiders)) : ?>
				<div class="card-body text-muted">
					<?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_NO_DATA'); ?>
				</div>
				<?php else : ?>
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead class="table-light">
							<tr>
								<th class="text-center" style="width:40px">#</th>
								<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER'); ?></th>
								<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER_NUMBER'); ?></th>
								<th class="text-end"><?php echo Text::_('COM_WENDFOLD_COL_MILES'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($this->topRiders as $rank => $row) : ?>
							<tr>
								<td class="text-center text-muted"><?php echo $rank + 1; ?></td>
								<td><?php echo htmlspecialchars($row->member_name, ENT_QUOTES); ?></td>
								<td><small class="text-muted"><?php echo htmlspecialchars($row->member_number, ENT_QUOTES); ?></small></td>
								<td class="text-end fw-bold"><?php echo number_format((float) $row->total_miles, 1); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		</div>

	</div>

	<!-- ========================================================
		 NEW MEMBERS TABLE
		 ======================================================== -->
	<div class="card mb-4">
		<div class="card-header">
			<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_NEW_MEMBERS_HEADING'); ?></strong>
			<span class="badge bg-info ms-2"><?php echo count($this->newMembers); ?></span>
		</div>
		<?php if (empty($this->newMembers)) : ?>
		<div class="card-body text-muted">
			<?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_NO_NEW_MEMBERS'); ?>
		</div>
		<?php else : ?>
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead class="table-light">
					<tr>
						<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER'); ?></th>
						<th><?php echo Text::_('COM_WENDFOLD_COL_MEMBER_NUMBER'); ?></th>
						<th><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_COL_JOIN_DATE'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->newMembers as $row) : ?>
					<tr>
						<td><?php echo htmlspecialchars($row->member_name, ENT_QUOTES); ?></td>
						<td><small class="text-muted"><?php echo htmlspecialchars($row->member_number, ENT_QUOTES); ?></small></td>
						<td><?php echo htmlspecialchars(substr($row->join_date, 0, 10), ENT_QUOTES); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>

</div>

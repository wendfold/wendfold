<?php
/**
 * Wendfold - Event Attendance Trends Report Template
 *
 * Renders:
 *   - Date-range filter form
 *   - Summary cards (grand total, total events, overall avg, highest month)
 *   - Month-by-month table with inline bar visualization (no JS required)
 *   - Type-breakdown columns per month
 *   - Per-event detail table
 *   - Average attendance cards
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.framework');

/** @var \Wendfold\Component\Wendfold\Administrator\View\Attendancetrends\HtmlView $this */

$typeColumns = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBuckets::columns($this->monthlyByType);

// Bar chart: highest monthly total used to scale bar widths
$maxMonthly = !empty($this->monthlyTotals) ? max($this->monthlyTotals) : 1;
if ($maxMonthly < 1) { $maxMonthly = 1; }

// Build sorted list of all months present in data
$allMonths = array_unique(array_merge(
	array_keys($this->monthlyTotals),
	array_keys($this->monthlyByType)
));
sort($allMonths);

$exportUrl = Route::_(
	'index.php?option=com_wendfold&task=attendancetrends.exportcsv'
	. '&date_from=' . urlencode($this->filters->date_from)
	. '&date_to='   . urlencode($this->filters->date_to)
	. '&' . Session::getFormToken() . '=1',
	false
);

// Find month with highest attendance for the "peak" card
$peakMonth = '';
$peakCount = 0;
foreach ($this->monthlyTotals as $ym => $cnt) {
	if ($cnt > $peakCount) {
		$peakCount = $cnt;
		$peakMonth = $ym;
	}
}
?>

<div class="container-fluid p-3">

	<!-- Page heading -->
	<div class="row mb-3">
		<div class="col-12">
			<h2 class="mb-1">
				<span class="icon-chart-line me-2" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_TITLE'); ?>
			</h2>
			<p class="text-muted mb-0">
				<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_DESC'); ?>
			</p>
		</div>
	</div>

	<!-- ================================================================
		 DATE RANGE FILTER FORM
		 ================================================================ -->
	<div class="card mb-4">
		<div class="card-header bg-light">
			<strong><?php echo Text::_('COM_WENDFOLD_ACTIVITYREPORT_FILTER_HEADING'); ?></strong>
		</div>
		<div class="card-body">
			<form method="get"
				  action="<?php echo Route::_('index.php?option=com_wendfold&view=attendancetrends'); ?>"
				  class="row g-2 align-items-end">
				<input type="hidden" name="option" value="com_wendfold">
				<input type="hidden" name="view"   value="attendancetrends">

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
					<a href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm">
						<span class="icon-download me-1" aria-hidden="true"></span>
						<?php echo Text::_('COM_WENDFOLD_BTN_EXPORT_CSV'); ?>
					</a>
				</div>
			</form>
		</div>
		<div class="card-footer text-muted small">
			<?php echo Text::sprintf(
				'COM_WENDFOLD_ACTIVITYREPORT_RANGE_LABEL',
				$this->filters->date_from,
				$this->filters->date_to
			); ?>
		</div>
	</div>

	<?php if (empty($allMonths)) : ?>
		<div class="alert alert-info">
			<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_NO_DATA'); ?>
		</div>
	<?php else : ?>

	<!-- ================================================================
		 SUMMARY CARDS
		 ================================================================ -->
	<div class="row g-3 mb-4">

		<!-- Total Attendance -->
		<div class="col-sm-6 col-xl-3">
			<div class="card text-center border-primary h-100">
				<div class="card-body">
					<div class="display-5 fw-bold text-primary">
						<?php echo number_format($this->grandTotal); ?>
					</div>
					<div class="text-muted small mt-1">
						<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_CARD_TOTAL_ATTENDANCE'); ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Total Events -->
		<div class="col-sm-6 col-xl-3">
			<div class="card text-center border-secondary h-100">
				<div class="card-body">
					<div class="display-5 fw-bold text-secondary">
						<?php echo number_format($this->totalEvents); ?>
					</div>
					<div class="text-muted small mt-1">
						<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_CARD_TOTAL_EVENTS'); ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Overall Average -->
		<div class="col-sm-6 col-xl-3">
			<div class="card text-center border-success h-100">
				<div class="card-body">
					<div class="display-5 fw-bold text-success">
						<?php echo $this->averages->overall; ?>
					</div>
					<div class="text-muted small mt-1">
						<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_CARD_AVG_OVERALL'); ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Peak Month -->
		<div class="col-sm-6 col-xl-3">
			<div class="card text-center border-warning h-100">
				<div class="card-body">
					<div class="display-5 fw-bold text-warning">
						<?php echo number_format($peakCount); ?>
					</div>
					<div class="text-muted small mt-1">
						<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_CARD_PEAK'); ?>
						<?php if ($peakMonth) : ?>
							<br><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($peakMonth); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

	</div><!-- /summary cards -->

	<!-- ================================================================
		 MONTHLY ATTENDANCE TABLE WITH VISUAL BARS
		 ================================================================ -->
	<div class="card mb-4">
		<div class="card-header bg-primary text-white">
			<h5 class="mb-0">
				<span class="icon-calendar me-2" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_MONTHLY_HEADING'); ?>
			</h5>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-sm table-striped table-hover mb-0">
					<thead class="table-dark">
						<tr>
							<th style="width:100px"><?php echo Text::_('COM_WENDFOLD_COL_MONTH'); ?></th>
							<th style="width:60px" class="text-end"><?php echo Text::_('COM_WENDFOLD_COL_TOTAL'); ?></th>
							<?php foreach ($typeColumns as $column) :
								$columnLabel = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label($column['title']);
							?>
								<th style="width:70px" class="text-end d-none d-md-table-cell">
									<?php echo htmlspecialchars($columnLabel, ENT_QUOTES, 'UTF-8'); ?>
								</th>
							<?php endforeach; ?>
							<th><?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_COL_BAR'); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($allMonths as $ym) :
						$total   = $this->monthlyTotals[$ym] ?? 0;
						$byType  = $this->monthlyByType[$ym] ?? [];
						$barPct  = $maxMonthly > 0 ? round(($total / $maxMonthly) * 100) : 0;
						$monthLabel = date('M Y', mktime(0, 0, 0, (int) substr($ym, 5, 2), 1, (int) substr($ym, 0, 4)));
					?>
						<tr>
							<td class="align-middle fw-semibold small"><?php echo htmlspecialchars($monthLabel); ?></td>
							<td class="align-middle text-end fw-bold"><?php echo number_format($total); ?></td>
							<?php foreach ($typeColumns as $column) :
								$tc = (int) ($byType[$column['id']]['count'] ?? 0);
								$badge = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::adminBadgeClass($column['behavior']);
							?>
								<td class="align-middle text-end small d-none d-md-table-cell">
									<?php if ($tc > 0) : ?>
										<span class="badge <?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $tc; ?></span>
									<?php else : ?>
										<span class="text-muted">-</span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
							<td class="align-middle" style="min-width:120px">
								<div style="background:#dee2e6; border-radius:3px; height:18px; width:100%;">
									<div style="background:#0d6efd; border-radius:3px; height:18px; width:<?php echo $barPct; ?>%; min-width:<?php echo ($total > 0 ? 2 : 0); ?>px;"></div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="table-primary fw-bold">
							<td>Total</td>
							<td class="text-end"><?php echo number_format($this->grandTotal); ?></td>
							<?php foreach ($typeColumns as $column) :
								$typeTotal = 0;
								foreach ($this->monthlyByType as $monthTypes) {
									$typeTotal += (int) ($monthTypes[$column['id']]['count'] ?? 0);
								}
							?>
								<td class="text-end d-none d-md-table-cell"><?php echo number_format($typeTotal); ?></td>
							<?php endforeach; ?>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>

	<!-- ================================================================
		 AVERAGE ATTENDANCE BY TYPE
		 ================================================================ -->
	<div class="card mb-4">
		<div class="card-header bg-primary text-white">
			<h5 class="mb-0">
				<span class="icon-chart-bar me-2" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_AVERAGES_HEADING'); ?>
			</h5>
		</div>
		<div class="card-body">
			<div class="row g-3">
				<div class="col-sm-4 col-md-2">
					<div class="text-center p-2 border rounded">
						<div class="fs-4 fw-bold"><?php echo $this->averages->overall; ?></div>
						<div class="small text-muted">All Events</div>
					</div>
				</div>
				<?php foreach ($this->averages->by_type as $bucket) :
					$averageLabel = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($bucket['title'] ?? ''));
				?>
				<div class="col-sm-4 col-md-2">
					<div class="text-center p-2 border rounded">
						<div class="fs-4 fw-bold"><?php echo $bucket['average']; ?></div>
						<div class="small text-muted"><?php echo htmlspecialchars($averageLabel, ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- ================================================================
		 PER-EVENT DETAIL TABLE
		 ================================================================ -->
	<div class="card mb-4">
		<div class="card-header bg-primary text-white">
			<h5 class="mb-0">
				<span class="icon-list-2 me-2" aria-hidden="true"></span>
				<?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_DETAIL_HEADING'); ?>
			</h5>
		</div>
		<div class="card-body p-0">
			<?php if (empty($this->eventDetail)) : ?>
				<p class="text-muted p-3 mb-0"><?php echo Text::_('COM_WENDFOLD_ATTENDANCETRENDS_NO_DATA'); ?></p>
			<?php else : ?>
			<div class="table-responsive">
				<table class="table table-sm table-striped table-hover mb-0">
					<thead class="table-dark">
						<tr>
							<th><?php echo Text::_('COM_WENDFOLD_COL_DATE'); ?></th>
							<th><?php echo Text::_('COM_WENDFOLD_COL_TITLE'); ?></th>
							<th><?php echo Text::_('COM_WENDFOLD_COL_TYPE'); ?></th>
							<th class="text-end"><?php echo Text::_('COM_WENDFOLD_COL_ATTENDANCE'); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($this->eventDetail as $ev) :
						$typeCfg = [
							'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($ev->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							'badge' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::adminBadgeClass((int) ($ev->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
						];
					?>
						<tr>
							<td class="small text-nowrap">
								<?php echo DateOnly::format($ev->event_date, 'M j, Y'); ?>
							</td>
							<td>
								<a href="<?php echo Route::_(
									'index.php?option=com_wendfold&view=event&layout=edit&id=' . (int) $ev->event_id
								); ?>">
									<?php echo $this->escape($ev->title); ?>
								</a>
							</td>
							<td>
								<span class="badge <?php echo htmlspecialchars($typeCfg['badge'], ENT_QUOTES, 'UTF-8'); ?>">
									<?php echo $typeCfg['label']; ?>
								</span>
							</td>
							<td class="text-end fw-bold">
								<?php echo (int) $ev->attendance_count; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php endif; // end if allMonths not empty ?>

</div><!-- /container-fluid -->

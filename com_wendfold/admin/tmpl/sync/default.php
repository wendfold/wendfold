<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin template: National Sync
 * admin/tmpl/sync/default.php
 *
 * Fix: Bootstrap 5 tab wiring corrected.
 *   - Tab nav uses <a href="#panelId" data-bs-toggle="tab"> pattern.
 *   - Each .tab-pane id exactly matches the href anchor.
 *   - HTMLHelper::_('bootstrap.tab') called in HtmlView.php to load tab JS.
 *
 * Phase 12: White-label param support retained throughout.
 *
 * Color conventions:
 *   - Standard card headers:    bg-primary text-white
 *   - Lapsed Cleanup card:      bg-warning text-dark  (intentional caution)
 *   - Chapter Transfers card:   bg-danger text-white  (intentional destructive)
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;

// ------------------------------------------------------------
// Phase 12: White-label component params
// ------------------------------------------------------------
$wlParams         = ComponentHelper::getParams('com_wendfold');
$groupName        = (string) $wlParams->get('group_name',        'Chapter');
$memberIdLabel    = (string) $wlParams->get('member_id_label',   'Member Number');
$nationalOrgLabel = (string) $wlParams->get('national_org_label', 'National Organization');
$chapterLabel     = (string) $wlParams->get('chapter_label',     'Chapter');
?>

<div class="container-fluid">

	<!-- Page title -->
	<div class="row mb-3 align-items-center">
		<div class="col">
			<h2 class="mb-0">
				<?php echo htmlspecialchars($nationalOrgLabel, ENT_QUOTES, 'UTF-8'); ?>
				<?php echo Text::_('COM_WENDFOLD_SYNC_TITLE_SUFFIX'); ?>
			</h2>
		</div>
	</div>

	<!-- =====================================================
		 Bootstrap 5 Tab Navigation
		 IMPORTANT: Use <a href="#id" data-bs-toggle="tab">
		 The href="#id" must exactly match the tab-pane id.
		 Do NOT use data-bs-target on <button> in Joomla 6 --
		 the <a> href pattern is more reliable in Joomla 6's
		 Bootstrap asset pipeline.
	===================================================== -->
	<ul class="nav nav-tabs mb-3" id="syncTabs" role="tablist">

		<li class="nav-item" role="presentation">
			<a class="nav-link active"
			   id="sync-upload-tab"
			   href="#sync-upload"
			   data-bs-toggle="tab"
			   role="tab"
			   aria-controls="sync-upload"
			   aria-selected="true">
				<?php echo Text::_('COM_WENDFOLD_SYNC_TAB_UPLOAD'); ?>
			</a>
		</li>

		<li class="nav-item" role="presentation">
			<a class="nav-link"
			   id="sync-lapsed-tab"
			   href="#sync-lapsed"
			   data-bs-toggle="tab"
			   role="tab"
			   aria-controls="sync-lapsed"
			   aria-selected="false">
				<?php echo Text::_('COM_WENDFOLD_SYNC_TAB_LAPSED'); ?>
			</a>
		</li>

		<li class="nav-item" role="presentation">
			<a class="nav-link"
			   id="sync-transfers-tab"
			   href="#sync-transfers"
			   data-bs-toggle="tab"
			   role="tab"
			   aria-controls="sync-transfers"
			   aria-selected="false">
				<?php echo htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8'); ?>
				<?php echo Text::_('COM_WENDFOLD_SYNC_TAB_TRANSFERS_SUFFIX'); ?>
			</a>
		</li>

		<li class="nav-item" role="presentation">
			<a class="nav-link"
			   id="sync-history-tab"
			   href="#sync-history"
			   data-bs-toggle="tab"
			   role="tab"
			   aria-controls="sync-history"
			   aria-selected="false">
				<?php echo Text::_('COM_WENDFOLD_SYNC_TAB_HISTORY'); ?>
			</a>
		</li>

		<li class="nav-item" role="presentation">
			<a class="nav-link"
			   id="sync-scheduled-tab"
			   href="#sync-scheduled"
			   data-bs-toggle="tab"
			   role="tab"
			   aria-controls="sync-scheduled"
			   aria-selected="false">
				<?php echo Text::_('COM_WENDFOLD_SYNC_TAB_SCHEDULED'); ?>
			</a>
		</li>

	</ul><!-- .nav-tabs -->

	<!-- =====================================================
		 Tab Panes
		 Each id here must exactly match the href="#..." above.
	===================================================== -->
	<div class="tab-content" id="syncTabContent">

		<!-- ==================================================
			 TAB 1: CSV Upload
		================================================== -->
		<div class="tab-pane fade show active"
			 id="sync-upload"
			 role="tabpanel"
			 aria-labelledby="sync-upload-tab">

			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong>
						<?php echo htmlspecialchars($nationalOrgLabel, ENT_QUOTES, 'UTF-8'); ?>
						<?php echo Text::_('COM_WENDFOLD_SYNC_CSV_UPLOAD_HEADING_SUFFIX'); ?>
					</strong>
				</div>
				<div class="card-body">
					<p class="text-muted">
						<?php echo Text::_('COM_WENDFOLD_SYNC_CSV_INSTRUCTIONS'); ?>
					</p>
					<p class="text-muted">
						<?php echo Text::_('COM_WENDFOLD_SYNC_CSV_FORMAT_HELP'); ?>
					</p>

					<form action="<?php echo Route::_('index.php?option=com_wendfold&task=sync.upload'); ?>"
						  method="post"
						  enctype="multipart/form-data">

						<div class="mb-3">
							<label class="form-label" for="sync_csv_file">
								<?php echo Text::_('COM_WENDFOLD_SYNC_CSV_FILE_LABEL'); ?>
							</label>
							<input type="file"
								   name="csv_file"
								   id="sync_csv_file"
								   class="form-control"
								   accept=".csv"
								   required
							/>
						</div>

						<div class="form-check mb-3">
							<input class="form-check-input"
								   type="checkbox"
								   name="dry_run"
								   id="sync_dry_run"
								   value="1">
							<label class="form-check-label" for="sync_dry_run">
								<?php echo Text::_('COM_WENDFOLD_SYNC_DRY_RUN_LABEL'); ?>
							</label>
							<div class="form-text">
								<?php echo Text::_('COM_WENDFOLD_SYNC_DRY_RUN_DESC'); ?>
							</div>
						</div>

						<button type="submit" class="btn btn-outline-primary me-2" name="preview" value="1">
							<span class="icon-eye" aria-hidden="true"></span>
							<?php echo Text::_('COM_WENDFOLD_SYNC_PREVIEW_BTN'); ?>
						</button>
						<button type="submit" class="btn btn-primary">
							<span class="icon-upload" aria-hidden="true"></span>
							<?php echo Text::_('COM_WENDFOLD_SYNC_CSV_UPLOAD_BTN'); ?>
						</button>

						<?php echo HTMLHelper::_('form.token'); ?>
					</form>
				</div>
			</div>

<?php if (!empty($this->importPreview) && is_array($this->importPreview))
{
	$preview = $this->importPreview;
	$csvFieldLabels = [
		'member_id'       => 'COM_WENDFOLD_CSV_COL_MEMBER_ID',
		'first_name'      => 'COM_WENDFOLD_CSV_COL_FIRST_NAME',
		'last_name'       => 'COM_WENDFOLD_CSV_COL_LAST_NAME',
		'email'           => 'COM_WENDFOLD_CSV_COL_EMAIL',
		'phone'           => 'COM_WENDFOLD_CSV_COL_PHONE',
		'address'         => 'COM_WENDFOLD_CSV_COL_ADDRESS',
		'city'            => 'COM_WENDFOLD_CSV_COL_CITY',
		'state'           => 'COM_WENDFOLD_CSV_COL_STATE',
		'postal_code'     => 'COM_WENDFOLD_CSV_COL_POSTAL_CODE',
		'country'         => 'COM_WENDFOLD_CSV_COL_COUNTRY',
		'join_date'       => 'COM_WENDFOLD_CSV_COL_JOIN_DATE',
		'expiry_date'     => 'COM_WENDFOLD_CSV_COL_EXPIRY_DATE',
		'membership_type' => 'COM_WENDFOLD_CSV_COL_MEMBERSHIP_TYPE',
		'life_member'     => 'COM_WENDFOLD_CSV_COL_LIFE_MEMBER',
		'status'          => 'COM_WENDFOLD_CSV_COL_STATUS',
		'chapter'         => 'COM_WENDFOLD_CSV_COL_CHAPTER',
	];
	$matchedFields = [];

	foreach ($preview['mapping'] ?? [] as $mapRow)
	{
		if (!empty($mapRow['matched']))
		{
			$matchedFields[] = (string) ($mapRow['field'] ?? '');
		}
	}

	echo '<div class="card mb-3">';
	echo '<div class="card-header bg-primary text-white">';
	echo '<strong>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_HEADING') . '</strong>';
	echo '</div>';
	echo '<div class="card-body">';
	echo '<dl class="row mb-3">';
	echo '<dt class="col-sm-4">' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_DELIMITER') . '</dt>';
	echo '<dd class="col-sm-8">' . htmlspecialchars((string) ($preview['delimiter_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</dd>';
	echo '<dt class="col-sm-4">' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_ENCODING') . '</dt>';
	echo '<dd class="col-sm-8">' . Text::_(!empty($preview['bom']) ? 'COM_WENDFOLD_SYNC_BOM_YES' : 'COM_WENDFOLD_SYNC_BOM_NO') . '</dd>';
	echo '<dt class="col-sm-4">' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_HEADER_LINE') . '</dt>';
	echo '<dd class="col-sm-8">' . (int) ($preview['header_line'] ?? 0) . '</dd>';
	echo '</dl>';

	if (!empty($preview['headers']) && is_array($preview['headers']))
	{
		echo '<p><strong>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_HEADERS') . '</strong> ';
		echo htmlspecialchars(implode(', ', $preview['headers']), ENT_QUOTES, 'UTF-8');
		echo '</p>';
	}

	if (!empty($preview['unmapped']) && is_array($preview['unmapped']))
	{
		echo '<p class="text-muted">' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_UNMAPPED') . ' ';
		echo htmlspecialchars(implode(', ', $preview['unmapped']), ENT_QUOTES, 'UTF-8');
		echo '</p>';
	}

	if (!empty($preview['errors']) && is_array($preview['errors']))
	{
		echo '<div class="alert alert-danger"><ul class="mb-0">';

		foreach ($preview['errors'] as $previewError)
		{
			echo '<li>' . htmlspecialchars((string) $previewError, ENT_QUOTES, 'UTF-8') . '</li>';
		}

		echo '</ul></div>';
	}

	if (!empty($preview['warnings']) && is_array($preview['warnings']))
	{
		echo '<div class="alert alert-warning"><ul class="mb-0">';

		foreach ($preview['warnings'] as $previewWarning)
		{
			echo '<li>' . htmlspecialchars((string) $previewWarning, ENT_QUOTES, 'UTF-8') . '</li>';
		}

		echo '</ul></div>';
	}

	if (!empty($preview['mapping']) && is_array($preview['mapping']))
	{
		echo '<div class="table-responsive mb-3">';
		echo '<table class="table table-sm table-striped mb-0">';
		echo '<thead class="table-light"><tr>';
		echo '<th>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_FIELD') . '</th>';
		echo '<th>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_CONFIGURED') . '</th>';
		echo '<th>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_RESOLVED') . '</th>';
		echo '<th>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_MATCH') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($preview['mapping'] as $mapRow)
		{
			$field = (string) ($mapRow['field'] ?? '');
			$via = (string) ($mapRow['via'] ?? '');
			$matched = !empty($mapRow['matched']);
			$viaLabel = $via === 'alias' ? 'COM_WENDFOLD_SYNC_MAP_ALIAS' : 'COM_WENDFOLD_SYNC_MAP_CONFIGURED';
			$matchText = $matched ? Text::_($viaLabel) : Text::_('COM_WENDFOLD_SYNC_MAP_MISSING');
			$fieldLabel = Text::_($csvFieldLabels[$field] ?? 'COM_WENDFOLD_SYNC_CSV_FILE_LABEL');

			echo '<tr>';
			echo '<td>' . $fieldLabel . '</td>';
			echo '<td>' . htmlspecialchars((string) ($mapRow['configured'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
			echo '<td>' . htmlspecialchars((string) ($mapRow['resolved'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
			echo '<td>' . $matchText . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	if (!empty($preview['sample']) && is_array($preview['sample']) && $matchedFields !== [])
	{
		echo '<h3 class="h5">' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_SAMPLE') . '</h3>';
		echo '<div class="table-responsive">';
		echo '<table class="table table-sm table-bordered mb-0">';
		echo '<thead class="table-light"><tr>';
		echo '<th>' . Text::_('COM_WENDFOLD_SYNC_PREVIEW_ROW') . '</th>';

		foreach ($matchedFields as $field)
		{
			$fieldLabel = Text::_($csvFieldLabels[$field] ?? 'COM_WENDFOLD_SYNC_CSV_FILE_LABEL');
			echo '<th>' . $fieldLabel . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ($preview['sample'] as $sampleRow)
		{
			$bad = is_array($sampleRow['bad_fields'] ?? null) ? $sampleRow['bad_fields'] : [];
			$sampleValues = is_array($sampleRow['values'] ?? null) ? $sampleRow['values'] : [];

			echo '<tr><td>' . (int) ($sampleRow['line'] ?? 0) . '</td>';

			foreach ($matchedFields as $field)
			{
				$cellClass = in_array($field, $bad, true) ? 'table-danger' : '';
				$cellValue = htmlspecialchars((string) ($sampleValues[$field] ?? ''), ENT_QUOTES, 'UTF-8');
				echo '<td class="' . $cellClass . '">' . $cellValue . '</td>';
			}

			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	echo '</div></div>';
}
?>

			<!-- Last sync summary -->
			<?php if (!empty($this->lastSync)) : ?>
			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong><?php echo Text::_('COM_WENDFOLD_SYNC_LAST_RESULT_HEADING'); ?></strong>
				</div>
				<div class="card-body">
					<dl class="row mb-0">
						<dt class="col-sm-4"><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_DATE'); ?></dt>
						<dd class="col-sm-8"><?php echo htmlspecialchars($this->lastSync->sync_date ?? '-', ENT_QUOTES, 'UTF-8'); ?></dd>

						<dt class="col-sm-4"><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_PROCESSED'); ?></dt>
						<dd class="col-sm-8"><?php echo (int) ($this->lastSync->records_processed ?? 0); ?></dd>

						<dt class="col-sm-4"><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_UPDATED'); ?></dt>
						<dd class="col-sm-8"><?php echo (int) ($this->lastSync->records_updated ?? 0); ?></dd>

						<dt class="col-sm-4"><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_ERRORS'); ?></dt>
						<dd class="col-sm-8"><?php echo (int) ($this->lastSync->records_errored ?? 0); ?></dd>

						<?php if (!empty($this->lastSync->notes)) : ?>
						<dt class="col-sm-4"><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_NOTES'); ?></dt>
						<dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($this->lastSync->notes, ENT_QUOTES, 'UTF-8')); ?></dd>
						<?php endif; ?>
					</dl>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- #sync-upload -->

		<!-- ==================================================
			 TAB 2: Lapsed Cleanup
		================================================== -->
		<div class="tab-pane fade"
			 id="sync-lapsed"
			 role="tabpanel"
			 aria-labelledby="sync-lapsed-tab">

			<!-- Intentional bg-warning: caution / destructive action -->
			<div class="card mb-3">
				<div class="card-header bg-warning text-dark">
					<strong><?php echo Text::_('COM_WENDFOLD_SYNC_LAPSED_HEADING'); ?></strong>
				</div>
				<div class="card-body">
					<p class="text-muted">
						<?php echo Text::_('COM_WENDFOLD_SYNC_LAPSED_DESCRIPTION'); ?>
					</p>

					<form action="<?php echo Route::_('index.php?option=com_wendfold&task=sync.runLapsedCleanup'); ?>"
						  method="post">
						<button type="submit"
								class="btn btn-warning"
								data-confirm="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_SYNC_LAPSED_CONFIRM')); ?>">
							<span class="icon-trash" aria-hidden="true"></span>
							<?php echo Text::_('COM_WENDFOLD_SYNC_LAPSED_BTN'); ?>
						</button>
						<?php echo HTMLHelper::_('form.token'); ?>
					</form>
				</div>
			</div>

		</div><!-- #sync-lapsed -->

		<!-- ==================================================
			 TAB 3: Chapter Transfers
		================================================== -->
		<div class="tab-pane fade"
			 id="sync-transfers"
			 role="tabpanel"
			 aria-labelledby="sync-transfers-tab">

			<!-- Intentional bg-danger: destructive roster action -->
			<div class="card mb-3">
				<div class="card-header bg-danger text-white">
					<strong>
						<?php echo htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8'); ?>
						<?php echo Text::_('COM_WENDFOLD_SYNC_TRANSFERS_HEADING_SUFFIX'); ?>
					</strong>
				</div>
				<div class="card-body">
					<p class="text-muted">
						<?php echo sprintf(
							Text::_('COM_WENDFOLD_SYNC_TRANSFERS_DESCRIPTION'),
							htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8')
						); ?>
					</p>

					<?php if (empty($this->flaggedMembers)) : ?>
						<p class="text-muted"><?php echo Text::_('COM_WENDFOLD_SYNC_TRANSFERS_NONE'); ?></p>
					<?php else : ?>
						<div class="table-responsive">
							<table class="table table-striped table-hover mb-0">
								<thead class="table-light">
									<tr>
										<th><?php echo Text::_('COM_WENDFOLD_COL_NAME'); ?></th>
										<th><?php echo htmlspecialchars($memberIdLabel, ENT_QUOTES, 'UTF-8'); ?></th>
										<th>
											<?php echo htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8'); ?>
											<?php echo Text::_('COM_WENDFOLD_SYNC_COL_CHAPTER_ON_FILE_SUFFIX'); ?>
										</th>
										<th><?php echo Text::_('COM_WENDFOLD_SYNC_COL_ACTIONS'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($this->flaggedMembers as $member) : ?>
									<tr>
										<td><?php echo htmlspecialchars($member->name ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars($member->member_number ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
										<td>
											<span class="badge bg-warning text-dark">
												<?php echo htmlspecialchars($member->chapter_on_file ?? '', ENT_QUOTES, 'UTF-8'); ?>
											</span>
										</td>
										<td class="d-flex gap-2">
											<form action="<?php echo Route::_('index.php?option=com_wendfold&task=sync.keepMember'); ?>"
												  method="post"
												  style="display:inline">
												<input type="hidden" name="profile_id" value="<?php echo htmlspecialchars((int) $member->id, ENT_QUOTES, 'UTF-8'); ?>" />
												<button type="submit" class="btn btn-sm btn-outline-primary">
													<?php echo Text::_('COM_WENDFOLD_SYNC_KEEP_MEMBER_BTN'); ?>
												</button>
												<?php echo HTMLHelper::_('form.token'); ?>
											</form>
											<form action="<?php echo Route::_('index.php?option=com_wendfold&task=sync.removeFromRoster'); ?>"
												  method="post"
												  style="display:inline">
												<input type="hidden" name="profile_id" value="<?php echo htmlspecialchars((int) $member->id, ENT_QUOTES, 'UTF-8'); ?>" />
												<button type="submit"
														class="btn btn-sm btn-danger"
														data-confirm="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_SYNC_REMOVE_CONFIRM')); ?>">
													<?php echo Text::_('COM_WENDFOLD_SYNC_REMOVE_BTN'); ?>
												</button>
												<?php echo HTMLHelper::_('form.token'); ?>
											</form>
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- #sync-transfers -->

		<!-- ==================================================
			 TAB 4: Sync History
		================================================== -->
		<div class="tab-pane fade"
			 id="sync-history"
			 role="tabpanel"
			 aria-labelledby="sync-history-tab">

			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong><?php echo Text::_('COM_WENDFOLD_SYNC_HISTORY_HEADING'); ?></strong>
				</div>
				<div class="card-body p-0">
					<?php if (empty($this->syncLog)) : ?>
						<p class="text-muted p-3"><?php echo Text::_('COM_WENDFOLD_SYNC_HISTORY_NONE'); ?></p>
					<?php else : ?>
					<div class="table-responsive">
						<table class="table table-striped mb-0">
							<thead class="table-light">
								<tr>
									<th><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_DATE'); ?></th>
									<th><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_TYPE'); ?></th>
									<th><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_PROCESSED'); ?></th>
									<th><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_UPDATED'); ?></th>
									<th><?php echo Text::_('COM_WENDFOLD_SYNC_LOG_ERRORS'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$typeMap = [
									1 => Text::_('COM_WENDFOLD_SYNC_TYPE_CSV'),
									2 => Text::_('COM_WENDFOLD_SYNC_TYPE_LAPSED'),
									3 => Text::_('COM_WENDFOLD_SYNC_TYPE_EVENT_REMINDER'),
									4 => Text::_('COM_WENDFOLD_SYNC_TYPE_RENEWAL_REMINDER'),
									5 => Text::_('COM_WENDFOLD_SYNC_TYPE_AWARD'),
								];
								foreach ($this->syncLog as $log) :
								?>
								<tr>
									<td><?php echo htmlspecialchars($log->sync_date ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(
										$typeMap[(int) ($log->sync_type ?? 0)] ?? Text::_('COM_WENDFOLD_SYNC_TYPE_UNKNOWN'),
										ENT_QUOTES, 'UTF-8'
									); ?></td>
									<td><?php echo (int) ($log->records_processed ?? 0); ?></td>
									<td><?php echo (int) ($log->records_updated ?? 0); ?></td>
									<td><?php echo (int) ($log->records_errored ?? 0); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- #sync-history -->

		<!-- ==================================================
			 TAB 5: Scheduled Tasks
		================================================== -->
		<div class="tab-pane fade"
			 id="sync-scheduled"
			 role="tabpanel"
			 aria-labelledby="sync-scheduled-tab">

			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong><?php echo Text::_('COM_WENDFOLD_SYNC_SCHEDULED_HEADING'); ?></strong>
				</div>
				<div class="card-body">
					<p class="text-muted">
						<?php echo Text::_('COM_WENDFOLD_SYNC_SCHEDULED_DESCRIPTION'); ?>
					</p>
					<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_scheduler', false), ENT_QUOTES, 'UTF-8'); ?>"
					   class="btn btn-outline-primary"
					   target="_blank">
						<?php echo Text::_('COM_WENDFOLD_SYNC_SCHEDULED_MANAGE_BTN'); ?>
					</a>
				</div>
			</div>

		</div><!-- #sync-scheduled -->

	</div><!-- .tab-content -->

</div>

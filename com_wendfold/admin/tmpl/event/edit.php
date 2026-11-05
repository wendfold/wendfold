<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Event Edit Layout
 *
 * Uses native Bootstrap 5 tab markup (data-bs-toggle="tab") via WAM.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Administrator\Helper\EventMetricCredits;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
	->useScript('form.validate')
	->useScript('bootstrap.tab');

$isNew = ((int) ($this->item->id ?? 0) === 0);
$metricCredits = EventMetricCredits::formRows();

$statusLabels = [
	1 => 'COM_WENDFOLD_EVENT_STATUS_SCHEDULED',
	5 => 'COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH',
	4 => 'COM_WENDFOLD_EVENT_STATUS_CONFIRMED',
	3 => 'COM_WENDFOLD_EVENT_STATUS_CANCELLED',
	2 => 'COM_WENDFOLD_EVENT_STATUS_COMPLETED',
];
$statusColors = [1 => 'info', 4 => 'success', 5 => 'warning', 3 => 'danger', 2 => 'secondary'];
$status       = (int) ($this->item->status ?? 1);

// Render a field with label above input and optional description below.
$renderStackedField = function (string $name): void
{
	$field = $this->form->getField($name);

	if (!$field)
	{
		return;
	}

	echo '<div class="hc-field-stacked">';
	echo $this->form->getLabel($name);
	echo $this->form->getInput($name);

	if (($description = (string) $field->getAttribute('description')) !== '')
	{
		$descId = htmlspecialchars((string) $field->__get('id') . '-desc', ENT_QUOTES, 'UTF-8');
		echo '<div class="form-text" id="' . $descId . '">' . Text::_($description) . '</div>';
	}

	echo '</div>';
};
?>

<form action="<?php echo Route::_('index.php?option=com_wendfold&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
	  method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

	<style>
		/* Match the Member edit view: stacked labels, consistent spacing */
		.hc-field-stacked > label {
			display: block;
			margin-bottom: .25rem;
			font-weight: 600;
		}
	</style>

	<div class="row g-3">

		<!-- ===== MAIN COLUMN ===== -->
		<div class="col-lg-9">

			<!-- Tab Navigation -->
			<ul class="nav nav-tabs" id="eventTabs" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link active" id="tab-details-btn"
							data-bs-toggle="tab" data-bs-target="#tab-details"
							type="button" role="tab" aria-selected="true">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_DETAILS'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-workflow-btn"
							data-bs-toggle="tab" data-bs-target="#tab-workflow"
							type="button" role="tab" aria-selected="false">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_WORKFLOW'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-locations-btn"
							data-bs-toggle="tab" data-bs-target="#tab-locations"
							type="button" role="tab" aria-selected="false">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_LOCATIONS'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-ride-btn"
							data-bs-toggle="tab" data-bs-target="#tab-ride"
							type="button" role="tab" aria-selected="false">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_RIDE'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-notes-btn"
							data-bs-toggle="tab" data-bs-target="#tab-notes"
							type="button" role="tab" aria-selected="false">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_ADMIN_NOTES'); ?>
					</button>
				</li>
			</ul>

			<!-- Tab Content -->
			<div class="tab-content border border-top-0 rounded-bottom p-3 mb-3" id="eventTabsContent">

				<!-- TAB: Details -->
				<div class="tab-pane fade show active" id="tab-details" role="tabpanel" aria-labelledby="tab-details-btn">

					<div class="row">
						<div class="col-12 mb-3"><?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?></div>
					</div>

					<div class="row">
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('event_type_id'); ?></div>
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('is_riding_event'); ?></div>
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('event_date'); ?></div>
					</div>

					<div class="row">
						<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('ksu_time'); ?></div>
						<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('ksd_time'); ?></div>
						<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('status'); ?></div>
						<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('published'); ?></div>
					</div>

					<div class="row">
						<div class="col-12 mb-3"><?php $renderStackedField('description'); ?></div>
					</div>

				</div>

				<!-- TAB: Workflow -->
				<div class="tab-pane fade" id="tab-workflow" role="tabpanel" aria-labelledby="tab-workflow-btn">

					<div class="row">
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('workflow_mode'); ?></div>
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('audience_scope'); ?></div>
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('allow_rsvp'); ?></div>
					</div>

					<div class="row">
						<div class="col-12 col-md-4 mb-3"></div>
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('parent_event_id'); ?></div>
						<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('show_in_event_list'); ?></div>
					</div>

					<div id="hc-workflow-meeting" class="alert alert-secondary small mb-3">
						<?php echo Text::_('COM_WENDFOLD_EVENT_MEETING_DEFAULTS_NOTE'); ?>
					</div>

					<div id="hc-workflow-overnight">
						<hr class="my-4">
						<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_WORKFLOW_OVERNIGHT'); ?></h6>

						<div class="row">
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('start_date'); ?></div>
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('end_date'); ?></div>
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_booking_deadline'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_lead_id'); ?></div>
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_sweep_id'); ?></div>
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_booking_method'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_booking_code'); ?></div>
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_lodging_name'); ?></div>
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('trip_lodging_phone'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('trip_lodging_address'); ?></div>
							<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('trip_lodging_city'); ?></div>
							<div class="col-6 col-md-1 mb-3"><?php $renderStackedField('trip_lodging_state'); ?></div>
							<div class="col-6 col-md-2 mb-3"><?php $renderStackedField('trip_lodging_zip'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 mb-3"><?php $renderStackedField('trip_lodging_notes'); ?></div>
						</div>
					</div>

					<div id="hc-workflow-volunteer">
						<hr class="my-4">
						<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_WORKFLOW_VOLUNTEER'); ?></h6>

						<div class="row">
							<div class="col-12 col-md-4 mb-3"><?php $renderStackedField('volunteer_signup_open'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 mb-3"><?php $renderStackedField('volunteer_notes'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 mb-3"><?php $renderStackedField('volunteer_roles'); ?></div>
						</div>
					</div>

				</div>

				<!-- TAB: Locations -->
				<div class="tab-pane fade" id="tab-locations" role="tabpanel" aria-labelledby="tab-locations-btn">

					<div class="alert alert-info mb-3">
						<?php echo Text::_('COM_WENDFOLD_LOCATIONS_NOTE'); ?>
					</div>

					<!-- KSU -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_LOCATION_KSU_HEADING'); ?></h6>
					<div class="row">
						<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('ksu_location_id'); ?></div>
					</div>

					<div id="hc-ksu-custom" class="mb-4">
						<div class="row">
							<div class="col-12 mb-3"><?php $renderStackedField('ksu_location_custom'); ?></div>
						</div>
						<div class="row">
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('ksu_address'); ?></div>
							<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('ksu_city'); ?></div>
							<div class="col-6 col-md-1 mb-3"><?php $renderStackedField('ksu_state'); ?></div>
							<div class="col-6 col-md-2 mb-3"><?php $renderStackedField('ksu_zip'); ?></div>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="jform[save_ksu_location]" id="save_ksu_location" value="1">
							<label class="form-check-label small text-muted" for="save_ksu_location">
								<?php echo Text::_('COM_WENDFOLD_SAVE_AS_CHAPTER_STOP'); ?>
							</label>
						</div>
					</div>

					<!-- KSD -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_LOCATION_KSD_HEADING'); ?></h6>

					<div class="row align-items-end">
						<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('ksd_location_id'); ?></div>
						<div class="col-12 col-md-6 mb-3">
							<div class="form-check mt-4">
								<input class="form-check-input" type="checkbox" id="hc-ksd-same" value="1">
								<label class="form-check-label" for="hc-ksd-same">
									<?php echo Text::_('COM_WENDFOLD_KSD_SAME_AS_KSU'); ?>
								</label>
							</div>
						</div>
					</div>

					<div id="hc-ksd-custom" class="mb-4">
						<div class="row">
							<div class="col-12 mb-3"><?php $renderStackedField('ksd_location_custom'); ?></div>
						</div>
						<div class="row">
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('ksd_address'); ?></div>
							<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('ksd_city'); ?></div>
							<div class="col-6 col-md-1 mb-3"><?php $renderStackedField('ksd_state'); ?></div>
							<div class="col-6 col-md-2 mb-3"><?php $renderStackedField('ksd_zip'); ?></div>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="jform[save_ksd_location]" id="save_ksd_location" value="1">
							<label class="form-check-label small text-muted" for="save_ksd_location">
								<?php echo Text::_('COM_WENDFOLD_SAVE_AS_CHAPTER_STOP'); ?>
							</label>
						</div>
					</div>

					<!-- Event location (non-riding events) -->
					<h6 class="fw-semibold border-bottom pb-1 mb-2"><?php echo Text::_('COM_WENDFOLD_LOCATION_EVENT_HEADING'); ?></h6>
					<p class="text-muted small mb-3"><?php echo Text::_('COM_WENDFOLD_LOCATION_EVENT_NOTE'); ?></p>

					<div class="row">
						<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('event_location_id'); ?></div>
					</div>

					<div id="hc-eventloc-custom" class="mb-2">
						<div class="row">
							<div class="col-12 mb-3"><?php $renderStackedField('event_location_custom'); ?></div>
						</div>
						<div class="row">
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('event_address'); ?></div>
							<div class="col-12 col-md-3 mb-3"><?php $renderStackedField('event_city'); ?></div>
							<div class="col-6 col-md-1 mb-3"><?php $renderStackedField('event_state'); ?></div>
							<div class="col-6 col-md-2 mb-3"><?php $renderStackedField('event_zip'); ?></div>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="jform[save_event_location]" id="save_event_location" value="1">
							<label class="form-check-label small text-muted" for="save_event_location">
								<?php echo Text::_('COM_WENDFOLD_SAVE_AS_EVENT_VENUE'); ?>
							</label>
						</div>
					</div>

				</div>

				<!-- TAB: Ride Info -->
				<div class="tab-pane fade" id="tab-ride" role="tabpanel" aria-labelledby="tab-ride-btn">

					<?php
						$img = trim((string) ($this->item->image ?? ''));
						$gpx = trim((string) ($this->item->gpx_file ?? ''));
					?>

					<div class="row">
						<div class="col-12 mb-3"><?php $renderStackedField('image'); ?></div>
					</div>

					<?php if ($metricCredits !== []) : ?>
						<div class="mb-3">
							<h3 class="h5 mb-1"><?php echo Text::_('COM_WENDFOLD_METRIC_EVENT_CREDITS'); ?></h3>
							<p class="form-text"><?php echo Text::_('COM_WENDFOLD_METRIC_EVENT_CREDITS_DESC'); ?></p>
						</div>
						<div class="row">
							<?php foreach ($metricCredits as $credit) : ?>
								<?php
								$decimals = (int) $credit->decimals;
								$step = $decimals > 0 ? '0.' . str_repeat('0', $decimals - 1) . '1' : '1';
								$creditLabel = \Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) $credit->title);
								$creditUnit = \Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) $credit->unit_label);
								?>
								<div class="col-12 col-md-6 mb-3">
									<label class="form-label" for="metric_credit_<?php echo (int) $credit->id; ?>">
										<?php echo htmlspecialchars($creditLabel, ENT_QUOTES, 'UTF-8'); ?>
										<?php if ($creditUnit !== '') : ?>
											<span class="text-muted">(<?php echo htmlspecialchars($creditUnit, ENT_QUOTES, 'UTF-8'); ?>)</span>
										<?php endif; ?>
									</label>
									<input class="form-control" type="number" min="0" step="<?php echo htmlspecialchars($step, ENT_QUOTES, 'UTF-8'); ?>"
										name="jform[metric_credits][<?php echo (int) $credit->id; ?>]"
										id="metric_credit_<?php echo (int) $credit->id; ?>"
										value="<?php echo htmlspecialchars((string) $credit->credit_value, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<!-- Ride-specific fields (hidden when Show ride details = No) -->
					<div id="hc-ride-fields">
						<div class="row">
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('route_link'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('road_captain_id'); ?></div>
							<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('sweep_id'); ?></div>
						</div>

						<div class="row">
							<div class="col-12 mb-3">
								<?php if ($gpx) : ?>
									<div class="alert alert-info py-2">
										<?php echo Text::_('COM_WENDFOLD_GPX_CURRENT_FILE'); ?>
										<a href="<?php echo htmlspecialchars(
											rtrim(Joomla\CMS\Uri\Uri::root(), '/') . '/' . ltrim($gpx, '/'),
											ENT_QUOTES,
											'UTF-8'
										); ?>" target="_blank" rel="noopener">
											<?php echo htmlspecialchars(basename($gpx), ENT_QUOTES, 'UTF-8'); ?>
										</a>
									</div>
								<?php endif; ?>
								<?php $renderStackedField('gpx_file'); ?>
							</div>
						</div>
					</div>

					<hr class="my-4">

					<div class="row">
						<div class="col-12 col-md-6 mb-3"><?php $renderStackedField('capacity'); ?></div>
					</div>

				</div>

				<!-- TAB: Admin Notes -->
				<div class="tab-pane fade" id="tab-notes" role="tabpanel" aria-labelledby="tab-notes-btn">
					<div class="row">
						<div class="col-12 mb-3"><?php $renderStackedField('notes'); ?></div>
					</div>
				</div>

			</div><!-- .tab-content -->

		</div><!-- col-lg-9 -->

		<!-- ===== SIDEBAR ===== -->
		<div class="col-lg-3">

			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<h3 class="card-title mb-0"><?php echo Text::_('COM_WENDFOLD_EVENT_INFO'); ?></h3>
				</div>
				<div class="card-body">
					<?php if (!$isNew) : ?>
						<dl class="row mb-0">
							<dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_COL_ID'); ?></dt>
							<dd class="col-sm-7"><?php echo (int) $this->item->id; ?></dd>

							<dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_COL_STATUS'); ?></dt>
							<dd class="col-sm-7">
								<span class="badge bg-<?php echo htmlspecialchars($statusColors[$status] ?? 'secondary', ENT_QUOTES, 'UTF-8'); ?>">
									<?php echo Text::_($statusLabels[$status] ?? 'COM_WENDFOLD_EVENT_STATUS_SCHEDULED'); ?>
								</span>
							</dd>

							<?php if (!empty($this->item->event_date)) : ?>
								<dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_COL_DATE'); ?></dt>
								<dd class="col-sm-7"><?php echo DateOnly::format($this->item->event_date, 'M j, Y'); ?></dd>
							<?php endif; ?>
						</dl>
					<?php else : ?>
						<p class="text-muted mb-0 small"><?php echo Text::_('COM_WENDFOLD_EVENT_NEW_HINT'); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if (!$isNew && !empty($this->item->qr_token)) : ?>
			<div class="card mb-3">
				<div class="card-header bg-light">
					<h3 class="card-title mb-0 small"><?php echo Text::_('COM_WENDFOLD_COL_QR_TOKEN'); ?></h3>
				</div>
				<div class="card-body">
					<code class="small"><?php echo htmlspecialchars($this->item->qr_token, ENT_QUOTES, 'UTF-8'); ?></code>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- col-lg-3 -->

	</div><!-- .row -->

	<?php echo $this->form->renderFieldset('hidden'); ?>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>

</form>

<script>
document.addEventListener('DOMContentLoaded', function () {

	// Encourage picker use without breaking the calendar button.
	// (Joomla's calendar widget may not open when the input is marked readonly.)
	// Block manual typing/paste, but keep the input clickable for the picker.
	var dateInput = document.getElementById('jform_event_date');
	if (dateInput) {
		dateInput.setAttribute('autocomplete', 'off');
		dateInput.addEventListener('keydown', function (e) {
			// Allow navigation and common shortcuts.
			var k = e.key || '';
			if (k === 'Tab' || k === 'Escape' || k === 'Enter' || k.indexOf('Arrow') === 0) {
				return;
			}
			if (e.ctrlKey || e.metaKey || e.altKey) {
				return;
			}
			e.preventDefault();
		});
		dateInput.addEventListener('paste', function (e) {
			e.preventDefault();
		});
	}

	function getRadioValue(name) {
		var els = document.querySelectorAll('input[name="' + name + '"]');
		for (var i = 0; i < els.length; i++) {
			if (els[i].checked) {
				return els[i].value;
			}
		}
		return null;
	}

	function setRadioValue(name, value) {
		var els = document.querySelectorAll('input[name="' + name + '"]');
		for (var i = 0; i < els.length; i++) {
			if (els[i].value === value) {
				els[i].checked = true;
			}
		}
	}

	// Auto-default "Show ride details" based on Event Type (Ride => Yes; other => No).
	var typeSel = document.getElementById('jform_event_type_id');
	var rideName = 'jform[is_riding_event]';
	var rideBox = document.getElementById('hc-ride-fields');

	function updateRideFields() {
		if (!rideBox) {
			return;
		}
		var v = getRadioValue(rideName);
		rideBox.style.display = (v === '1') ? '' : 'none';
	}

	var isNewEvent = <?php echo $isNew ? 'true' : 'false'; ?>;
	var workflowSel = document.getElementById('jform_workflow_mode');
	var audienceSel = document.getElementById('jform_audience_scope');
	var allowRsvpName = 'jform[allow_rsvp]';
	var suppressMeetingDirty = false;
	var meetingFieldDirty = {
		workflow: false,
		audience: false,
		allow: false,
		ride: false
	};

	function selectedType() {
		var opt = (typeSel && typeSel.selectedIndex >= 0) ? typeSel.options[typeSel.selectedIndex] : null;
		var behavior = opt ? (parseInt(opt.getAttribute('data-behavior') || '0', 10) || 0) : 0;
		var workflow = opt ? (opt.getAttribute('data-default-workflow') || '') : '';

		if (!workflow) {
			workflow = behavior === 1 ? '2' : (behavior === 2 ? '3' : (behavior === 3 ? '5' : '1'));
		}

		return {
			behavior: behavior,
			workflow: workflow,
			riding: opt ? (opt.getAttribute('data-default-riding') || '0') : '0'
		};
	}

	function applyMeetingDefaults() {
		if (!isNewEvent || !typeSel) {
			return;
		}

		var type = selectedType();

		suppressMeetingDirty = true;

		if (!meetingFieldDirty.workflow && workflowSel) {
			workflowSel.value = type.workflow;
		}

		if (type.behavior === 2) {
			if (!meetingFieldDirty.audience && audienceSel) {
				audienceSel.value = '2';
			}

			if (!meetingFieldDirty.allow) {
				setRadioValue(allowRsvpName, '1');
			}

			if (!meetingFieldDirty.ride) {
				setRadioValue(rideName, '0');
			}
		} else if (!meetingFieldDirty.ride) {
			var riding = type.behavior === 3 ? '0' : (type.riding === '1' ? '1' : '0');
			setRadioValue(rideName, riding);
		}

		suppressMeetingDirty = false;
		updateRideFields();
	}

	if (typeSel) {
		typeSel.addEventListener('change', function () {
			applyMeetingDefaults();
			toggleWorkflowSections();
		});
	}

	if (workflowSel) {
		workflowSel.addEventListener('change', function () {
			if (!suppressMeetingDirty) {
				meetingFieldDirty.workflow = true;
			}
			toggleWorkflowSections();
		});
	}

	if (audienceSel) {
		audienceSel.addEventListener('change', function () {
			if (!suppressMeetingDirty) {
				meetingFieldDirty.audience = true;
			}
		});
	}

	var allowRsvpRadios = document.querySelectorAll('input[name="' + allowRsvpName + '"]');
	for (var a = 0; a < allowRsvpRadios.length; a++) {
		allowRsvpRadios[a].addEventListener('change', function () {
			if (!suppressMeetingDirty) {
				meetingFieldDirty.allow = true;
			}
		});
	}

	var rideRadios = document.querySelectorAll('input[name="' + rideName + '"]');
	for (var r = 0; r < rideRadios.length; r++) {
		rideRadios[r].addEventListener('change', function () {
			if (!suppressMeetingDirty) {
				meetingFieldDirty.ride = true;
			}
			updateRideFields();
		});
	}

	// Location helpers: show custom fields only when the dropdown is "Custom".
	function toggleCustom(selectId, containerId, customNameId) {
		var sel = document.getElementById(selectId);
		var box = document.getElementById(containerId);
		if (!sel || !box) {
			return;
		}

		var show = (sel.value === '-1');
		if (!show && customNameId) {
			var nameEl = document.getElementById(customNameId);
			if (nameEl && nameEl.value && nameEl.value.trim()) {
				show = true;
			}
		}
		box.style.display = show ? '' : 'none';
	}

	function setDisabled(containerId, disabled) {
		var box = document.getElementById(containerId);
		if (!box) {
			return;
		}
		var fields = box.querySelectorAll('input, select, textarea, button');
		for (var i = 0; i < fields.length; i++) {
			fields[i].disabled = disabled;
		}
	}

	function toggleWorkflowSections() {
		var overnightBox = document.getElementById('hc-workflow-overnight');
		var meetingBox = document.getElementById('hc-workflow-meeting');
		var volunteerBox = document.getElementById('hc-workflow-volunteer');
		var workflowValue = workflowSel ? workflowSel.value : '';
		var type = selectedType();

		// Behavior 3 shows overnight fields. Workflow 5 on another type does not.
		if (overnightBox) {
			overnightBox.style.display = (type.behavior === 3) ? '' : 'none';
		}

		if (meetingBox) {
			meetingBox.style.display = (type.behavior === 2) ? '' : 'none';
		}

		if (volunteerBox) {
			volunteerBox.style.display = (workflowValue === '4') ? '' : 'none';
		}
	}

	// KSD same-as-KSU helper.
	var same = document.getElementById('hc-ksd-same');
	var ksuSelId = 'jform_ksu_location_id';
	var ksdSelId = 'jform_ksd_location_id';

	function copyKsuToKsd() {
		var ksuSel = document.getElementById(ksuSelId);
		var ksdSel = document.getElementById(ksdSelId);
		if (ksuSel && ksdSel) {
			ksdSel.value = ksuSel.value;
		}

		var pairs = [
			['jform_ksu_location_custom', 'jform_ksd_location_custom'],
			['jform_ksu_address', 'jform_ksd_address'],
			['jform_ksu_city', 'jform_ksd_city'],
			['jform_ksu_state', 'jform_ksd_state'],
			['jform_ksu_zip', 'jform_ksd_zip']
		];
		for (var i = 0; i < pairs.length; i++) {
			var a = document.getElementById(pairs[i][0]);
			var b = document.getElementById(pairs[i][1]);
			if (a && b) {
				b.value = a.value;
			}
		}

		toggleCustom(ksdSelId, 'hc-ksd-custom', 'jform_ksd_location_custom');
	}

	function updateSameAs() {
		if (!same) {
			return;
		}

		var enabled = same.checked;
		var ksdSel = document.getElementById(ksdSelId);
		if (ksdSel) {
			ksdSel.disabled = enabled;
		}
		setDisabled('hc-ksd-custom', enabled);

		if (enabled) {
			copyKsuToKsd();
		}
	}

	function detectSameAs() {
		if (!same) {
			return;
		}
		var ksuSel = document.getElementById(ksuSelId);
		var ksdSel = document.getElementById(ksdSelId);

		// Back-compat / bugfix: previous versions disabled KSD fields when "same as" was checked,
		// which prevented the KSD values from being posted and persisted.
		// If KSD is empty but KSU is selected and KSD custom/address fields are empty, assume
		// the user intended "KSD same as KSU" and reflect that in the UI.
		if (ksuSel && ksdSel && ksuSel.value && !ksdSel.value) {
			var ksdCustom = document.getElementById('jform_ksd_location_custom');
			var ksdAddr   = document.getElementById('jform_ksd_address');
			var ksdCity   = document.getElementById('jform_ksd_city');
			var ksdState  = document.getElementById('jform_ksd_state');
			var ksdZip    = document.getElementById('jform_ksd_zip');
			var allBlank  = true;

			[ksdCustom, ksdAddr, ksdCity, ksdState, ksdZip].forEach(function (el) {
				if (el && String(el.value || '').trim() !== '') {
					allBlank = false;
				}
			});

			if (allBlank) {
				same.checked = true;
				copyKsuToKsd();
			}
		}

		if (ksuSel && ksdSel && ksuSel.value && ksuSel.value === ksdSel.value) {
			same.checked = true;
		}
		updateSameAs();
	}

	['jform_ksu_location_id', 'jform_ksd_location_id', 'jform_event_location_id'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) {
			el.addEventListener('change', function () {
				toggleCustom('jform_ksu_location_id', 'hc-ksu-custom', 'jform_ksu_location_custom');
				toggleCustom('jform_ksd_location_id', 'hc-ksd-custom', 'jform_ksd_location_custom');
				toggleCustom('jform_event_location_id', 'hc-eventloc-custom', 'jform_event_location_custom');
				updateSameAs();
			});
		}
	});

	if (same) {
		same.addEventListener('change', updateSameAs);
	}

	// Ensure KSD values are posted when "same as" is enabled.
	// (Disabled inputs are not submitted.)
	var form = document.getElementById('adminForm');
	if (form) {
		form.addEventListener('submit', function () {
			if (!same || !same.checked) {
				return;
			}

			// Re-enable fields right before submit so the values persist.
			var ksdSel = document.getElementById(ksdSelId);
			if (ksdSel) {
				ksdSel.disabled = false;
			}
			setDisabled('hc-ksd-custom', false);

			// Copy again to guarantee the posted values match KSU.
			copyKsuToKsd();
		});
	}

	toggleCustom('jform_ksu_location_id', 'hc-ksu-custom', 'jform_ksu_location_custom');
	toggleCustom('jform_ksd_location_id', 'hc-ksd-custom', 'jform_ksd_location_custom');
	toggleCustom('jform_event_location_id', 'hc-eventloc-custom', 'jform_event_location_custom');
	detectSameAs();
	applyMeetingDefaults();
	updateRideFields();
	toggleWorkflowSections();
});
</script>

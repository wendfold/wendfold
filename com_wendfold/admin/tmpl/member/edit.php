<?php
/**
 * Admin Template: Member Edit - Tabbed Layout (reorganized)
 * admin/tmpl/member/edit.php
 *
 * Tabs (5):
 *   1. Profile               - current photo, preferred name, shirt size, sponsor, emergency contact
 *   2. Chapter Status        - dues + waiver + national (combined)
 *   3. {groupName} Membership- member number, type, status, dates
 *   4. Motorcycles           - inline list
 *   5. Admin Notes           - internal notes
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Wendfold\Component\Wendfold\Administrator\View\Member\HtmlView $this */

$params           = Joomla\CMS\Component\ComponentHelper::getParams('com_wendfold');
$groupName        = $params->get('group_name',         'Chapter');
$memberIdLabel    = $params->get('member_id_label',    'Member Number');
$nationalOrgLabel = $params->get('national_org_label', 'National Organization');
$chapterLabel     = $params->get('chapter_label',      'Chapter');

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
	->useScript('form.validate')
	->useScript('bootstrap.tab');

$isNew = empty($this->item->id);

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

<div class="container-fluid">
<form action="<?php echo Route::_('index.php?option=com_wendfold&layout=edit&id=' . (int) $this->item->id); ?>"
	  method="post"
	  name="adminForm"
	  id="adminForm"
	  class="form-validate">

	<style>
		/* Match the Event edit view: stacked labels, consistent spacing */
		.hc-field-stacked > label {
			display: block;
			margin-bottom: .25rem;
			font-weight: 600;
		}
	</style>

	<div class="row mb-3">
		<div class="col">
			<h2 class="mb-0">
				<?php echo $isNew ? Text::_('COM_WENDFOLD_MEMBER_ADD_TITLE') : Text::_('COM_WENDFOLD_MEMBER_EDIT_TITLE'); ?>
			</h2>
		</div>
	</div>

	<div class="row g-3">
		<div class="col-lg-9">

			<!-- Joomla User Account -->
			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong><?php echo Text::_('COM_WENDFOLD_FIELDSET_USER'); ?></strong>
				</div>
				<div class="card-body">
					<?php echo $this->form->renderField('user_id'); ?>
				</div>
			</div>

			<!-- Tab Navigation -->
			<ul class="nav nav-tabs" id="memberTabs" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link active" id="tab-profile-btn"
							data-bs-toggle="tab" data-bs-target="#tab-profile"
							type="button" role="tab" aria-selected="true">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_PROFILE'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-status-btn"
							data-bs-toggle="tab" data-bs-target="#tab-status"
							type="button" role="tab" aria-selected="false">
						<?php echo Text::_('COM_WENDFOLD_FIELDSET_CHAPTER_STATUS'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-membership-btn"
							data-bs-toggle="tab" data-bs-target="#tab-membership"
							type="button" role="tab" aria-selected="false">
						<?php echo htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') . ' ' . Text::_('COM_WENDFOLD_FIELDSET_MEMBERSHIP_SUFFIX'); ?>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="tab-motos-btn"
							data-bs-toggle="tab" data-bs-target="#tab-motos"
							type="button" role="tab" aria-selected="false">
						<?php echo Text::_('COM_WENDFOLD_MOTORCYCLES_HEADING'); ?>
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
			<div class="tab-content border border-top-0 rounded-bottom p-3 mb-3" id="memberTabsContent">

				<!-- ========================================================
					 TAB 1: Profile
				======================================================== -->
				<div class="tab-pane fade show active" id="tab-profile" role="tabpanel">

					<!-- Profile Photo (read-only; members change it on the site) -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_MEMBER_PHOTO_SECTION'); ?></h6>
<?php
$photo   = trim((string) ($this->item->photo ?? ''));
$hashPos = strpos($photo, '#');

if ($hashPos !== false)
{
	$photo = trim(substr($photo, 0, $hashPos));
}

$photoUrl = '';

if ($photo !== '')
{
	$isRemote  = str_contains($photo, '..')
		|| str_starts_with($photo, '//')
		|| preg_match('#^[a-z][a-z0-9+.-]*:#i', $photo) === 1;
	$extension = strtolower(pathinfo($photo, PATHINFO_EXTENSION));

	if ($isRemote === false && in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true))
	{
		$photoUrl = Uri::root() . ltrim($photo, '/');
	}
}

echo '<div class="mb-4">';

if ($photoUrl !== '')
{
	echo '<img src="' . htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') . '"'
		. ' alt="' . htmlspecialchars(Text::_('COM_WENDFOLD_MEMBER_PHOTO_CURRENT_ALT'), ENT_QUOTES, 'UTF-8') . '"'
		. ' class="img-thumbnail d-block mb-2"'
		. ' style="max-width:160px;max-height:160px;object-fit:cover;" loading="lazy">';
}
elseif ($photo !== '')
{
	echo '<p class="mb-2">'
		. Text::_('COM_WENDFOLD_MEMBER_PHOTO_CURRENT_PATH') . ': '
		. htmlspecialchars($photo, ENT_QUOTES, 'UTF-8')
		. '</p>';
}

echo '<p class="form-text mb-0">' . Text::_('COM_WENDFOLD_MEMBER_PHOTO_SITE_NOTE') . '</p>';
echo '</div>';
?>

					<!-- Profile -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_PROFILE'); ?></h6>
					<div class="row mb-3">
						<div class="col-md-6 mb-3"><?php $renderStackedField('preferred_name'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('shirt_size'); ?></div>
					</div>
					<div class="row mb-3">
						<div class="col-md-6 mb-3"><?php $renderStackedField('sponsor_id'); ?></div>
					</div>

					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_RIDE_ROLES'); ?></h6>
					<div class="row mb-3">
						<div class="col-md-6 mb-3"><?php $renderStackedField('is_road_captain'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('is_road_crew'); ?></div>
					</div>

					<!-- Emergency Contact -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_EMERGENCY'); ?></h6>
					<p class="text-muted small mb-3"><?php echo Text::_('COM_WENDFOLD_EMERGENCY_NOTE'); ?></p>
					<div class="row mb-3">
						<div class="col-md-4 mb-3"><?php $renderStackedField('emergency_name'); ?></div>
						<div class="col-md-4 mb-3"><?php $renderStackedField('emergency_phone'); ?></div>
						<div class="col-md-4 mb-3"><?php $renderStackedField('emergency_relation'); ?></div>
					</div>

				</div><!-- #tab-profile -->

				<!-- ========================================================
					 TAB 2: Chapter Status
					 Chapter Dues + Liability Waiver + National Verification
				======================================================== -->
				<div class="tab-pane fade" id="tab-status" role="tabpanel">

					<!-- Dues -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_DUES'); ?></h6>
					<div class="row mb-3">
						<div class="col-md-6 mb-3"><?php $renderStackedField('dues_paid'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('dues_paid_date'); ?></div>
					</div>

					<!-- Waiver -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_WAIVER'); ?></h6>
					<div class="row mb-3">
						<div class="col-md-4 mb-3"><?php $renderStackedField('waiver_signed'); ?></div>
						<div class="col-md-4 mb-3"><?php $renderStackedField('waiver_signed_date'); ?></div>
						<div class="col-md-4 mb-3"><?php $renderStackedField('waiver_version'); ?></div>
					</div>

					<!-- National Verification -->
					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo htmlspecialchars($nationalOrgLabel, ENT_QUOTES, 'UTF-8') . ' ' . Text::_('COM_WENDFOLD_FIELDSET_NATIONAL_SUFFIX'); ?></h6>
					<div class="row mb-3">
						<div class="col-md-6 mb-3"><?php $renderStackedField('national_verified'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('national_verified_date'); ?></div>
					</div>

					<?php if (!$isNew) : ?>
						<div class="row small text-muted">
							<?php if (!empty($this->item->national_sync_date) && $this->item->national_sync_date !== '0000-00-00 00:00:00') : ?>
								<div class="col-md-4 mb-2">
									<span class="d-block"><?php echo Text::_('COM_WENDFOLD_MEMBER_LAST_SYNC'); ?></span>
									<strong class="text-body"><?php echo HTMLHelper::_('date', $this->item->national_sync_date, 'M j, Y'); ?></strong>
								</div>
							<?php endif; ?>
							<?php if (!empty($this->item->chapter_on_file)) : ?>
								<div class="col-md-4 mb-2">
									<span class="d-block"><?php echo $chapterLabel . ' ' . Text::_('COM_WENDFOLD_SYNC_COL_CHAPTER_ON_FILE_SUFFIX'); ?></span>
									<strong class="text-body"><?php echo $this->escape($this->item->chapter_on_file); ?></strong>
								</div>
							<?php endif; ?>
							<?php if (!empty($this->item->chapter_switch_flagged) && (int) $this->item->chapter_switch_flagged === 1) : ?>
								<div class="col-md-4 mb-2">
									<span class="badge bg-warning text-dark"><?php echo Text::_('COM_WENDFOLD_MEMBER_CHAPTER_SWITCH_FLAGGED'); ?></span>
								</div>
							<?php endif; ?>
							<?php if (!empty($this->item->reminder_60_sent)) : ?>
								<div class="col-md-4 mb-2">
									<span class="d-block"><?php echo Text::_('COM_WENDFOLD_MEMBER_REMINDER_60_SENT'); ?></span>
									<strong class="text-body"><?php echo DateOnly::format($this->item->reminder_60_sent, 'M j, Y'); ?></strong>
								</div>
							<?php endif; ?>
							<?php if (!empty($this->item->reminder_30_sent)) : ?>
								<div class="col-md-4 mb-2">
									<span class="d-block"><?php echo Text::_('COM_WENDFOLD_MEMBER_REMINDER_30_SENT'); ?></span>
									<strong class="text-body"><?php echo DateOnly::format($this->item->reminder_30_sent, 'M j, Y'); ?></strong>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				</div><!-- #tab-status -->

				<!-- ========================================================
					 TAB 3: Membership
				======================================================== -->
				<div class="tab-pane fade" id="tab-membership" role="tabpanel">

					<div class="hc-field-stacked mb-3">
						<label class="form-label fw-semibold" for="jform_member_number"><?php echo htmlspecialchars($memberIdLabel, ENT_QUOTES, 'UTF-8'); ?></label>
						<?php echo $this->form->getInput('member_number'); ?>
						<?php $desc = $this->form->getFieldAttribute('member_number', 'description'); ?>
						<?php if ($desc) : ?>
							<?php $memberNumberId = htmlspecialchars('jform_member_number-desc', ENT_QUOTES, 'UTF-8'); ?>
							<div class="form-text" id="<?php echo $memberNumberId; ?>"><?php echo Text::_($desc); ?></div>
						<?php endif; ?>
					</div>

					<div class="row">
						<div class="col-md-6 mb-3"><?php $renderStackedField('membership_type_id'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('published'); ?></div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3"><?php $renderStackedField('member_since'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('chapter_join_date'); ?></div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3"><?php $renderStackedField('renewal_date'); ?></div>
						<div class="col-md-6 mb-3"><?php $renderStackedField('renewal_reminders_optout'); ?></div>
					</div>

				</div><!-- #tab-membership -->

				<!-- ========================================================
					 TAB 4: Motorcycles
				======================================================== -->
				<div class="tab-pane fade" id="tab-motos" role="tabpanel">

					<?php if ($isNew) : ?>
						<div class="alert alert-info mb-0"><?php echo Text::_('COM_WENDFOLD_MOTORCYCLE_NOTE'); ?></div>
					<?php else : ?>
						<div class="d-flex justify-content-between align-items-center mb-3">
							<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=motorcycle&layout=edit&id=0&profile_id=' . (int) $this->item->id, false), ENT_QUOTES, 'UTF-8'); ?>"
							   class="btn btn-success btn-sm">
								<span class="icon-plus" aria-hidden="true"></span>
								<?php echo Text::_('COM_WENDFOLD_MOTORCYCLE_ADD_BTN'); ?>
							</a>
							<span class="text-muted small"><?php echo Text::_('COM_WENDFOLD_MOTORCYCLE_NOTE'); ?></span>
						</div>

						<?php if (empty($this->motorcycles)) : ?>
							<p class="text-muted"><?php echo Text::_('COM_WENDFOLD_MOTORCYCLES_NONE_FOUND'); ?></p>
						<?php else : ?>
							<div class="table-responsive">
								<table class="table table-sm table-striped table-hover mb-0">
									<thead class="table-light">
										<tr>
											<th><?php echo Text::_('COM_WENDFOLD_COL_YEAR'); ?></th>
											<th><?php echo Text::_('COM_WENDFOLD_COL_MOTORCYCLE'); ?></th>
											<th><?php echo Text::_('COM_WENDFOLD_FIELD_MOTO_COLOR_LABEL'); ?></th>
											<th><?php echo Text::_('COM_WENDFOLD_FIELD_MOTO_NICKNAME_LABEL'); ?></th>
											<th class="text-center"><?php echo Text::_('COM_WENDFOLD_MOTORCYCLES_COL_PRIMARY'); ?></th>
											<th class="text-center"><?php echo Text::_('COM_WENDFOLD_COL_STATUS'); ?></th>
											<th width="70"></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ($this->motorcycles as $moto) : ?>
										<tr>
											<td><?php echo $moto->year ?: ' - '; ?></td>
											<td><?php echo htmlspecialchars(trim($moto->make . ' ' . $moto->model), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo $moto->color ? htmlspecialchars($moto->color, ENT_QUOTES, 'UTF-8') : ' - '; ?></td>
											<td><?php echo $moto->nickname ? htmlspecialchars($moto->nickname, ENT_QUOTES, 'UTF-8') : ' - '; ?></td>
											<td class="text-center"><?php echo $moto->is_primary ? '<span class="badge bg-success">Yes</span>' : ''; ?></td>
											<td class="text-center"><?php echo $moto->published ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
											<td class="text-center">
												<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=motorcycle&layout=edit&id=' . (int) $moto->id, false), ENT_QUOTES, 'UTF-8'); ?>"
												   class="btn btn-outline-primary btn-sm">
													<span class="icon-edit" aria-hidden="true"></span>
													<?php echo Text::_('JACTION_EDIT'); ?>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					<?php endif; ?>

				</div><!-- #tab-motos -->

				<!-- ========================================================
					 TAB 5: Admin Notes
				======================================================== -->
				<div class="tab-pane fade" id="tab-notes" role="tabpanel">
					<div class="mb-3">
						<?php echo $this->form->getInput('notes'); ?>
						<div class="form-text"><?php echo Text::_('COM_WENDFOLD_FIELD_NOTES_DESC'); ?></div>
					</div>
				</div><!-- #tab-notes -->

			</div><!-- .tab-content -->

		</div><!-- col-lg-9 -->

		<!-- RIGHT COLUMN -->
		<div class="col-lg-3">

			<?php if (!$isNew) : ?>
			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong><?php echo Text::_('COM_WENDFOLD_MEMBER_DETAILS'); ?></strong>
				</div>
				<div class="card-body">
					<dl class="row mb-0 small">
						<dt class="col-5"><?php echo Text::_('COM_WENDFOLD_COL_ID'); ?></dt>
						<dd class="col-7"><?php echo (int) $this->item->id; ?></dd>
						<?php if (!empty($this->item->created)) : ?>
							<dt class="col-5"><?php echo Text::_('JGLOBAL_FIELD_CREATED_LABEL'); ?></dt>
							<dd class="col-7"><?php echo HTMLHelper::_('date', $this->item->created, 'M j, Y'); ?></dd>
						<?php endif; ?>
						<?php if (!empty($this->item->modified) && $this->item->modified !== '0000-00-00 00:00:00') : ?>
							<dt class="col-5"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'); ?></dt>
							<dd class="col-7"><?php echo HTMLHelper::_('date', $this->item->modified, 'M j, Y'); ?></dd>
						<?php endif; ?>
					</dl>
				</div>
			</div>
			<?php endif; ?>

			<?php if (!$isNew && !empty($this->item->chapter_switch_flagged) && (int) $this->item->chapter_switch_flagged === 1) : ?>
			<div class="card mb-3 border-warning">
				<div class="card-header bg-warning text-dark">
					<strong><?php echo Text::_('COM_WENDFOLD_MEMBER_CHAPTER_SWITCH_FLAGGED'); ?></strong>
				</div>
				<div class="card-body small">
					<p class="mb-1"><?php echo Text::_('COM_WENDFOLD_MEMBER_CHAPTER_SWITCH_NOTE'); ?></p>
					<?php if (!empty($this->item->chapter_on_file)) : ?>
						<p class="mb-0"><?php echo $chapterLabel . ' ' . Text::_('COM_WENDFOLD_SYNC_COL_CHAPTER_ON_FILE_SUFFIX'); ?>:
							<strong><?php echo $this->escape($this->item->chapter_on_file); ?></strong></p>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="card mb-3">
				<div class="card-header bg-light">
					<strong><?php echo Text::_('JSTATUS'); ?></strong>
				</div>
				<div class="card-body small text-muted">
					<?php echo Text::_('COM_WENDFOLD_MEMBER_STATUS_HINT'); ?>
				</div>
			</div>

		</div><!-- col-lg-3 -->

	</div><!-- .row -->

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>

</form>
</div>

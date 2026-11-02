<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * Officer edit template
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;


/** @var \Wendfold\Component\Wendfold\Administrator\View\Officer\HtmlView $this */

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
	->useScript('form.validate');

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
<?php // FIX: form action was missing &view=officer - Joomla could not route saves or cancel correctly ?>
<div class="container-fluid">
<form action="<?php echo Route::_('index.php?option=com_wendfold&view=officer&layout=edit&id=' . (int) $this->item->id); ?>"
	  method="post" name="adminForm" id="adminForm" class="form-validate">

	<style>
		/* Stacked labels like the Member tabbed edit view */
		.hc-field-stacked > label {
			display: block;
			margin-bottom: .25rem;
			font-weight: 600;
		}
	</style>

	<div class="row mb-3">
		<div class="col">
			<h2 class="mb-0">
				<?php echo $isNew ? Text::_('COM_WENDFOLD_OFFICER_NEW') : Text::_('COM_WENDFOLD_OFFICER_EDIT'); ?>
			</h2>
		</div>
	</div>

	<div class="row g-3">

		<div class="col-lg-9">
			<div class="card mb-3">
				<div class="card-header bg-primary text-white">
					<strong><?php echo Text::_('COM_WENDFOLD_OFFICER_FIELDSET_DETAILS'); ?></strong>
				</div>
				<div class="card-body">

					<?php // Member preview card when editing an existing record ?>
					<?php if (!$isNew && !empty($this->item->member_name)) : ?>
						<div class="alert alert-secondary d-flex align-items-center mb-3">
							<?php
							// Profile photos are stored as site-relative paths (e.g. images/wendfold/profiles/x.jpg).
							// In the administrator, relative URLs would resolve under /administrator and 404.
							$photo = trim((string) ($this->item->photo ?? ''));
							$photoUrl = '';

							if ($photo !== '')
							{
								// Keep external URLs as-is.
								if (preg_match('#^https?://#i', $photo))
								{
									$photoUrl = $photo;
								}
								else
								{
									$photoUrl = Uri::root() . ltrim($photo, '/');
								}
							}
							?>
							<?php if ($photoUrl !== '') : ?>
								<img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES); ?>"
									 alt="<?php echo htmlspecialchars($this->item->member_name, ENT_QUOTES); ?>"
									 class="rounded-circle me-3"
									 style="width:50px;height:50px;object-fit:cover;"
									 loading="lazy"
									 onerror="this.style.display='none'">
							<?php endif; ?>
							<div>
								<strong><?php echo $this->escape($this->item->member_name); ?></strong><br>
								<small class="text-muted">
									<?php echo Text::_('COM_WENDFOLD_LABEL_MEMBER_HASH'); ?>
									<?php echo $this->escape($this->item->member_number ?? ''); ?>
								</small>
							</div>
						</div>
					<?php endif; ?>

					<div class="row g-3 mb-4">
						<div class="col-md-6">
							<?php $renderStackedField('profile_id'); ?>
						</div>
						<div class="col-md-6">
							<?php $renderStackedField('role_id'); ?>
						</div>

						<div class="col-md-4">
							<?php $renderStackedField('role_order'); ?>
						</div>
						<div class="col-md-4">
							<?php $renderStackedField('is_current'); ?>
						</div>
						<div class="col-md-4">
							<?php $renderStackedField('published'); ?>
						</div>
					</div>

					<h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_OFFICERS_HEADING_TERM'); ?></h6>
					<div class="row g-3 mb-4">
						<div class="col-md-6">
							<?php $renderStackedField('term_start'); ?>
						</div>
						<div class="col-md-6">
							<?php $renderStackedField('term_end'); ?>
						</div>
					</div>

					<?php $renderStackedField('notes'); ?>

				</div>
			</div>
		</div>

		<div class="col-lg-3">

			<div class="card mb-3">
				<div class="card-header bg-light">
					<?php echo Text::_('COM_WENDFOLD_OFFICER_METADATA'); ?>
				</div>
				<div class="card-body">
					<?php if (!$isNew) : ?>
						<dl class="row mb-0 small">
							<dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_COL_ID'); ?></dt>
							<dd class="col-sm-7"><?php echo (int) $this->item->id; ?></dd>

							<?php if (!empty($this->item->created)) : ?>
								<dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_OFFICER_FIELD_CREATED_LABEL'); ?></dt>
								<dd class="col-sm-7"><?php echo HTMLHelper::_('date', $this->item->created, 'M j, Y'); ?></dd>
							<?php endif; ?>

							<?php if (!empty($this->item->modified)) : ?>
								<dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_OFFICER_FIELD_MODIFIED_LABEL'); ?></dt>
								<dd class="col-sm-7"><?php echo HTMLHelper::_('date', $this->item->modified, 'M j, Y'); ?></dd>
							<?php endif; ?>
						</dl>
					<?php else : ?>
						<p class="text-muted small mb-0">
							<?php echo Text::_('COM_WENDFOLD_OFFICER_METADATA_NEW'); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="card mb-3">
				<div class="card-header bg-light">
					<?php echo Text::_('COM_WENDFOLD_OFFICER_QUICK_INFO'); ?>
				</div>
				<div class="card-body small text-muted">
					<p class="mb-1"><?php echo Text::_('COM_WENDFOLD_OFFICER_QUICK_INFO_HINT'); ?></p>
					<p class="mb-0"><?php echo Text::_('COM_WENDFOLD_OFFICER_QUICK_INFO_DATES'); ?></p>
				</div>
			</div>

		</div>
	</div>

	<?php echo $this->form->getInput('id'); ?>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
</div>

<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricDisplayLabel;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricEntryTitle;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;

/** @var \Wendfold\Component\Wendfold\Site\View\Activitylog\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
?>
<div class="hc-page">
	<h1><?php echo Text::_('COM_WENDFOLD_ACTIVITY_PAGE_TITLE'); ?></h1>
	<?php if (!$this->profile) : ?>
		<div class="alert alert-warning"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_NO_PROFILE'); ?></div>
	<?php elseif ($this->metrics === []) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_NO_METRICS'); ?></div>
	<?php else : ?>
		<form action="<?php echo Route::_('index.php?option=com_wendfold&task=activitylog.save'); ?>" method="post" class="form-validate">
			<div class="mb-3">
				<label class="form-label" for="activity_date"><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_DATE'); ?></label>
				<input class="form-control required" type="date" name="activity_date" id="activity_date" required
					value="<?php echo $this->escape($this->today); ?>">
			</div>
			<div class="mb-3">
				<label class="form-label" for="activity_title"><?php echo Text::_('JGLOBAL_TITLE'); ?></label>
				<input class="form-control required" type="text" name="title" id="activity_title" required maxlength="255">
			</div>
			<div class="mb-3">
				<label class="form-label" for="activity_description"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_NOTES'); ?></label>
				<textarea class="form-control" name="description" id="activity_description" rows="3"></textarea>
			</div>
			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="start_location"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_START'); ?></label>
					<input class="form-control" type="text" name="start_location" id="start_location" maxlength="255">
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="end_location"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_END'); ?></label>
					<input class="form-control" type="text" name="end_location" id="end_location" maxlength="255">
				</div>
			</div>
			<?php if ($this->motorcycles !== []) : ?>
				<div class="mb-3">
					<label class="form-label" for="motorcycle_id"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_MOTORCYCLE'); ?></label>
					<select class="form-select" name="motorcycle_id" id="motorcycle_id">
						<option value="0"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_MOTORCYCLE_NONE'); ?></option>
						<?php foreach ($this->motorcycles as $motorcycle) : ?>
							<?php
							$label = trim((string) $motorcycle->nickname);
							if ($label === '')
							{
								$label = trim((string) $motorcycle->model_year . ' ' . $motorcycle->make . ' ' . $motorcycle->model);
							}
							?>
							<option value="<?php echo (int) $motorcycle->id; ?>"><?php echo $this->escape($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>
			<?php foreach ($this->metrics as $metric) : ?>
				<?php
				$step = '1';
				$decimals = (int) $metric->decimals;
				if ($decimals > 0)
				{
					$step = '0.' . str_repeat('0', $decimals - 1) . '1';
				}
				$metricLabel = MetricDisplayLabel::withOptionalUnit(
					(string) $metric->title,
					(string) $metric->unit_label
				);
				?>
				<div class="mb-3">
					<label class="form-label" for="metric_<?php echo (int) $metric->id; ?>">
						<?php echo $this->escape($metricLabel); ?>
					</label>
					<input class="form-control" type="number" min="0" step="<?php echo $this->escape($step); ?>"
						name="metrics[<?php echo (int) $metric->id; ?>]" id="metric_<?php echo (int) $metric->id; ?>">
					<?php if ((int) $metric->member_entry_needs_approval === 1) : ?>
						<div class="form-text"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_NEEDS_APPROVAL'); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<button type="submit" class="btn btn-primary"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_SAVE'); ?></button>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
	<?php endif; ?>

	<?php if ($this->profile) : ?>
		<section class="mt-4">
			<h2><?php echo Text::_('COM_WENDFOLD_ACTIVITY_LOG_RECENT'); ?></h2>
			<?php if ($this->recentEntries === []) : ?>
				<p class="text-muted mb-0"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_LOG_RECENT_EMPTY'); ?></p>
			<?php else : ?>
				<div class="table-responsive">
					<table class="table table-striped mb-0">
						<thead>
							<tr>
								<th scope="col"><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_DATE'); ?></th>
								<th scope="col"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_TITLE'); ?></th>
								<th scope="col"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_METRIC'); ?></th>
								<th scope="col"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_VALUE'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($this->recentEntries as $entry) : ?>
							<?php
							$decimals = max(0, min(3, (int) $entry->decimals));
							$amount = number_format((float) $entry->value, $decimals, '.', '');
							$metricTitle = LookupTitle::display((string) $entry->metric_title);
							$entryTitle = MetricEntryTitle::display(
								isset($entry->activity_title) ? (string) $entry->activity_title : null
							);
							$valueText = MetricDisplayLabel::formatAmountWithUnit(
								$amount,
								(float) $entry->value,
								(string) $entry->unit_label,
								$metricTitle
							);
							?>
							<tr>
								<td><?php echo $this->escape(DateOnly::format((string) $entry->entry_date, 'M d, Y')); ?></td>
								<td>
									<?php if ($entryTitle !== '') : ?>
										<?php echo $this->escape($entryTitle); ?>
									<?php else : ?>
										<span class="text-muted"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_NOTE_NONE'); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo $this->escape($metricTitle); ?></td>
								<td><?php echo $this->escape($valueText); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>

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
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
/** @var \Wendfold\Component\Wendfold\Administrator\View\Activities\HtmlView $this */

$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'me.entry_date'));
$listDirn = $this->escape((string) StateValue::get($this->state, 'list.direction', 'DESC'));
?>
<form action="<?php echo Route::_('index.php?option=com_wendfold&view=activities'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row mb-3">
		<div class="col-md-6 d-flex gap-2">
			<input type="text" name="filter_search" id="filter_search" class="form-control"
				placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
				value="<?php echo $this->escape((string) StateValue::get($this->state, 'filter.search', '')); ?>">
			<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
		</div>
	</div>
	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_EMPTY'); ?></div>
	<?php else : ?>
		<table class="table table-striped">
			<thead>
				<tr>
					<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
					<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_ACTIVITIES_DATE', 'me.entry_date', $listDirn, $listOrder); ?></th>
					<th><?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_TITLE', 'activity_title', $listDirn, $listOrder); ?></th>
					<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_ACTIVITIES_MEMBER', 'member_name', $listDirn, $listOrder); ?></th>
					<th><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_VALUES'); ?></th>
					<th><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_APPROVAL'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($this->items as $i => $item) : ?>
				<tr>
					<td class="text-center">
						<?php if ((int) $item->id > 0) : ?>
							<?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?>
						<?php endif; ?>
					</td>
					<td><?php echo $this->escape(DateOnly::format((string) $item->activity_date, 'M d, Y')); ?></td>
					<td>
						<?php
						$entryTitle = MetricEntryTitle::display(
							isset($item->title) ? (string) $item->title : null
						);
						?>
						<?php if ($entryTitle !== '') : ?>
							<?php echo $this->escape($entryTitle); ?>
						<?php else : ?>
							<span class="text-muted"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_NOTE_NONE'); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php echo $this->escape((string) $item->member_name); ?>
						<?php if ((string) $item->member_number !== '') : ?>
							<span class="text-muted"><?php echo $this->escape((string) $item->member_number); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if (empty($item->metric_entries)) : ?>
							<?php echo Text::_('COM_WENDFOLD_ACTIVITIES_NO_VALUES'); ?>
						<?php else : ?>
							<?php foreach ($item->metric_entries as $entry) : ?>
								<?php
								$decimals = max(0, min(3, (int) $entry->decimals));
								$amount = number_format((float) $entry->value, $decimals, '.', '');
								$entryTitle = LookupTitle::display((string) $entry->title);
								$valueText = MetricDisplayLabel::formatAmountWithUnit(
									$amount,
									(float) $entry->value,
									(string) $entry->unit_label,
									$entryTitle
								);
								$published = (int) $entry->published;

								if ($published === 1)
								{
									$entryState = 'COM_WENDFOLD_ACTIVITIES_COUNTED';
								}
								elseif ($published === -2)
								{
									$entryState = 'COM_WENDFOLD_ACTIVITIES_REJECTED_STATE';
								}
								else
								{
									$entryState = 'COM_WENDFOLD_ACTIVITIES_PENDING';
								}
								?>
								<div>
									<?php echo $this->escape($entryTitle); ?>:
									<?php echo $this->escape($valueText); ?>
									<span class="text-muted">(<?php echo Text::_($entryState); ?>)</span>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$activityPublished = (int) $item->published;

						if ($activityPublished === -2)
						{
							$approvalKey = 'COM_WENDFOLD_ACTIVITIES_REJECTED_STATE';
						}
						elseif ($activityPublished === 0 || (int) $item->pending_entries > 0)
						{
							$approvalKey = 'COM_WENDFOLD_ACTIVITIES_PENDING';
						}
						elseif ((int) $item->counted_entries > 0 || $activityPublished === 1)
						{
							$approvalKey = 'COM_WENDFOLD_ACTIVITIES_COUNTED';
						}
						else
						{
							$approvalKey = 'COM_WENDFOLD_ACTIVITIES_NO_VALUES';
						}
						?>
						<?php echo Text::_($approvalKey); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>
	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

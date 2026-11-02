<?php

/**
 * Wendfold - Chapter Management System
 *
 * Shared list for membership types, officer roles, and event types.
 * State changes are posted with the form token.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
$lookup = $this->lookup;
$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'a.ordering'));
$listDirn = $this->escape((string) StateValue::get($this->state, 'list.direction', 'ASC'));
$saveOrder = $listOrder === 'a.ordering';
$user = Factory::getApplication()->getIdentity();
$canEdit = $user->authorise('core.edit', 'com_wendfold');
$canCreate = $user->authorise('core.create', 'com_wendfold');
$saveOrderingUrl = 'index.php?option=com_wendfold&task=' . $lookup['listTask']
	. '.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';

if ($saveOrder) {
	HTMLHelper::_('draggablelist.draggable');
}

$workflowLabels = [
	1 => 'COM_WENDFOLD_WORKFLOW_STANDARD_RSVP',
	2 => 'COM_WENDFOLD_WORKFLOW_RIDE_OPS',
	3 => 'COM_WENDFOLD_WORKFLOW_MEETING_ROLLCALL',
	4 => 'COM_WENDFOLD_WORKFLOW_VOLUNTEER',
	5 => 'COM_WENDFOLD_WORKFLOW_OVERNIGHT',
];
$behaviorLabels = [
	EventTypeBehavior::Ride->value      => 'COM_WENDFOLD_EVENT_BEHAVIOR_RIDE',
	EventTypeBehavior::Meeting->value   => 'COM_WENDFOLD_EVENT_BEHAVIOR_MEETING',
	EventTypeBehavior::Overnight->value => 'COM_WENDFOLD_EVENT_BEHAVIOR_OVERNIGHT',
	EventTypeBehavior::General->value   => 'COM_WENDFOLD_EVENT_BEHAVIOR_GENERAL',
];
$periodTypeLabels = [
	1 => 'COM_WENDFOLD_AWARD_PERIOD_LIFETIME',
	2 => 'COM_WENDFOLD_AWARD_PERIOD_CALENDAR_YEAR',
	3 => 'COM_WENDFOLD_AWARD_PERIOD_ROLLING',
	4 => 'COM_WENDFOLD_AWARD_PERIOD_CUSTOM',
];
$matchModeLabels = [
	1 => 'COM_WENDFOLD_AWARD_MATCH_ALL',
	2 => 'COM_WENDFOLD_AWARD_MATCH_ANY',
];
$repeatModeLabels = [
	0 => 'COM_WENDFOLD_AWARD_REPEAT_ONCE',
	1 => 'COM_WENDFOLD_AWARD_REPEAT_TIERED',
];
$columns = $lookup['columns'];
$colspan = 5 + \count($columns);
?>
<form action="<?php echo Route::_('index.php?option=com_wendfold&view=' . $lookup['view']); ?>"
	method="post" name="adminForm" id="adminForm">
	<div class="row mb-3">
		<div class="col-md-6 d-flex gap-2">
			<input type="text" name="filter_search" id="filter_search" class="form-control"
				placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
				value="<?php echo $this->escape((string) StateValue::get($this->state, 'filter.search', '')); ?>">
			<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button type="button" class="btn btn-secondary"
				onclick="document.getElementById('filter_search').value='';this.form.submit();">
				<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
			</button>
		</div>
		<div class="col-md-3">
			<select name="filter_published" class="form-select" onchange="this.form.submit()">
				<option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
				<option value="1"<?php echo (string) StateValue::get($this->state, 'filter.published') === '1' ? ' selected' : ''; ?>>
					<?php echo Text::_('JPUBLISHED'); ?>
				</option>
				<option value="0"<?php echo (string) StateValue::get($this->state, 'filter.published') === '0' ? ' selected' : ''; ?>>
					<?php echo Text::_('JUNPUBLISHED'); ?>
				</option>
			</select>
		</div>
	</div>

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_($lookup['empty']); ?></div>
		<?php if ($canCreate) : ?>
			<button type="submit" class="btn btn-primary" name="task" value="<?php echo $this->escape($lookup['itemTask']); ?>.add">
				<?php echo Text::_('JNEW'); ?>
			</button>
		<?php endif; ?>
	<?php else : ?>
		<table class="table table-striped" id="lookupList">
			<thead>
				<tr>
					<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
					<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ORDERING', 'a.ordering', $listDirn, $listOrder); ?></th>
					<th><?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
					<?php foreach ($columns as $column) : ?>
						<th><?php echo HTMLHelper::_('grid.sort', $column['heading'], $column['sort'], $listDirn, $listOrder); ?></th>
					<?php endforeach; ?>
					<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?></th>
					<th class="w-1"><?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?></th>
				</tr>
			</thead>
			<tbody<?php echo $saveOrder ? ' class="js-draggable" data-url="' . htmlspecialchars($saveOrderingUrl, ENT_QUOTES, 'UTF-8') . '" data-direction="' . strtolower($listDirn) . '" data-nested="false"' : ''; ?>>
			<?php foreach ($this->items as $i => $item) : ?>
				<tr class="row<?php echo $i % 2; ?>">
					<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', LookupTitle::display((string) $item->title)); ?></td>
					<td class="text-center">
						<?php if ($saveOrder) : ?>
							<span class="sortable-handler"><span class="icon-ellipsis-v" aria-hidden="true"></span></span>
						<?php endif; ?>
						<input type="text" name="order[]" size="5" class="width-20 text-area-order"
							value="<?php echo (int) $item->ordering; ?>"<?php echo $saveOrder ? '' : ' disabled'; ?>>
					</td>
					<td>
						<?php echo CheckoutAcl::checkedOutIcon($i, $item, $lookup['listTask'], $user); ?>
						<?php if ($canEdit) : ?>
							<a href="<?php echo Route::_('index.php?option=com_wendfold&task=' . $lookup['itemTask'] . '.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape(LookupTitle::display((string) $item->title)); ?>
							</a>
						<?php else : ?>
							<?php echo $this->escape(LookupTitle::display((string) $item->title)); ?>
						<?php endif; ?>
					</td>
					<?php foreach ($columns as $column) :
						$raw = $item->{$column['field']} ?? '';
						?>
						<td>
							<?php if ($column['kind'] === 'yesno') : ?>
								<?php echo Text::_((int) $raw === 1 ? 'JYES' : 'JNO'); ?>
							<?php elseif ($column['kind'] === 'lang') : ?>
								<?php echo $this->escape(LookupTitle::display((string) $raw)); ?>
							<?php elseif ($column['kind'] === 'aggregation') : ?>
								<?php
								$aggregationLabels = [
									1 => 'COM_WENDFOLD_METRIC_AGGREGATION_SUM',
									2 => 'COM_WENDFOLD_METRIC_AGGREGATION_COUNT',
									3 => 'COM_WENDFOLD_METRIC_AGGREGATION_MAX',
								];
								echo Text::_($aggregationLabels[(int) $raw] ?? 'COM_WENDFOLD_METRIC_AGGREGATION_SUM');
								?>
							<?php elseif ($column['kind'] === 'behavior') : ?>
								<?php echo Text::_($behaviorLabels[(int) $raw] ?? 'COM_WENDFOLD_EVENT_BEHAVIOR_GENERAL'); ?>
							<?php elseif ($column['kind'] === 'workflow') : ?>
								<?php echo Text::_($workflowLabels[(int) $raw] ?? 'COM_WENDFOLD_WORKFLOW_STANDARD_RSVP'); ?>
							<?php elseif ($column['kind'] === 'period_type') : ?>
								<?php echo Text::_($periodTypeLabels[(int) $raw] ?? 'COM_WENDFOLD_AWARD_PERIOD_LIFETIME'); ?>
							<?php elseif ($column['kind'] === 'match_mode') : ?>
								<?php echo Text::_($matchModeLabels[(int) $raw] ?? 'COM_WENDFOLD_AWARD_MATCH_ALL'); ?>
							<?php elseif ($column['kind'] === 'repeat_mode') : ?>
								<?php echo Text::_($repeatModeLabels[(int) $raw] ?? 'COM_WENDFOLD_AWARD_REPEAT_ONCE'); ?>
							<?php else : ?>
								<?php echo $this->escape((string) $raw); ?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
					<td class="text-center">
						<?php echo HTMLHelper::_('jgrid.published', (int) $item->published, $i, $lookup['listTask'] . '.', $user->authorise('core.edit.state', 'com_wendfold')); ?>
					</td>
					<td><?php echo (int) $item->id; ?></td>
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

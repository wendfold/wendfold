<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

// Database column names are snake_case on list rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
/** @var \Wendfold\Component\Wendfold\Administrator\View\Memberawards\HtmlView $this */
$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'ma.earned_at'));
$listDirn = $this->escape((string) StateValue::get($this->state, 'list.direction', 'DESC'));
$canEdit = (bool) $this->canEdit;

$stateLabels = [
	0 => 'COM_WENDFOLD_MEMBERAWARDS_STATE_REVOKED',
	1 => 'COM_WENDFOLD_MEMBERAWARDS_STATE_GRANTED',
	2 => 'COM_WENDFOLD_MEMBERAWARDS_STATE_REVIEW',
];
$sourceLabels = [
	1 => 'COM_WENDFOLD_MEMBERAWARDS_SOURCE_AUTO',
	2 => 'COM_WENDFOLD_MEMBERAWARDS_SOURCE_MANUAL',
];
?>
<?php if ($canEdit) : ?>
	<div class="card mb-3">
		<div class="card-header">
			<strong><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_TITLE'); ?></strong>
		</div>
		<div class="card-body">
			<form action="<?php echo Route::_('index.php?option=com_wendfold&task=memberawards.grant'); ?>"
				method="post" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label" for="grant_profile_id">
						<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_MEMBER'); ?>
					</label>
					<select name="profile_id" id="grant_profile_id" class="form-select" required>
						<option value=""><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_MEMBER_SELECT'); ?></option>
						<?php foreach ($this->memberOptions as $profileId => $memberName) : ?>
							<option value="<?php echo (int) $profileId; ?>">
								<?php echo $this->escape((string) $memberName); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="grant_award_id">
						<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_AWARD'); ?>
					</label>
					<select name="award_id" id="grant_award_id" class="form-select" required>
						<option value=""><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_AWARD_SELECT'); ?></option>
						<?php foreach ($this->awardOptions as $awardId => $awardTitle) : ?>
							<option value="<?php echo (int) $awardId; ?>">
								<?php echo $this->escape(LookupTitle::display((string) $awardTitle)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label" for="grant_period_key">
						<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_PERIOD'); ?>
					</label>
					<input type="text" name="period_key" id="grant_period_key" class="form-control"
						maxlength="20" placeholder="YYYY">
					<div class="form-text"><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_PERIOD_HINT'); ?></div>
				</div>
				<div class="col-md-1">
					<label class="form-label" for="grant_tier">
						<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_GRANT_TIER'); ?>
					</label>
					<input type="number" name="tier" id="grant_tier" class="form-control" min="1" value="1">
				</div>
				<div class="col-md-1">
					<button type="submit" class="btn btn-primary w-100">
						<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_BTN_GRANT'); ?>
					</button>
				</div>
				<?php echo HTMLHelper::_('form.token'); ?>
			</form>
		</div>
	</div>
<?php endif; ?>

<form action="<?php echo Route::_('index.php?option=com_wendfold&view=memberawards'); ?>"
	method="post" name="adminForm" id="adminForm">
	<div class="row mb-3">
		<div class="col-md-6 d-flex gap-2">
			<input type="text" name="filter_search" id="filter_search" class="form-control"
				placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
				value="<?php echo $this->escape((string) StateValue::get($this->state, 'filter.search', '')); ?>">
			<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button type="button" class="btn btn-secondary"
				onclick="document.getElementById('filter_search').value=''; this.form.submit();">
				<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
			</button>
		</div>
		<div class="col-md-3">
			<select name="filter_state" class="form-select" onchange="this.form.submit()">
				<option value=""><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_FILTER_STATE'); ?></option>
				<option value="1"<?php echo (string) StateValue::get($this->state, 'filter.state') === '1' ? ' selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_STATE_GRANTED'); ?>
				</option>
				<option value="0"<?php echo (string) StateValue::get($this->state, 'filter.state') === '0' ? ' selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_STATE_REVOKED'); ?>
				</option>
				<option value="2"<?php echo (string) StateValue::get($this->state, 'filter.state') === '2' ? ' selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_STATE_REVIEW'); ?>
				</option>
			</select>
		</div>
	</div>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php if (empty($this->items)) : ?>
	<div class="alert alert-info"><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_EMPTY'); ?></div>
<?php else : ?>
	<table class="table table-striped" id="memberAwardsList">
		<thead>
			<tr>
				<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_AWARD', 'award_title', $listDirn, $listOrder); ?></th>
				<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_MEMBER', 'member_name', $listDirn, $listOrder); ?></th>
				<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_PERIOD', 'ma.period_key', $listDirn, $listOrder); ?></th>
				<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_TIER', 'ma.tier', $listDirn, $listOrder); ?></th>
				<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_STATE', 'ma.state', $listDirn, $listOrder); ?></th>
				<th><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_COL_SOURCE'); ?></th>
				<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_EARNED', 'ma.earned_at', $listDirn, $listOrder); ?></th>
				<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBERAWARDS_COL_PRESENTED', 'ma.presented_on', $listDirn, $listOrder); ?></th>
				<th class="w-15"><?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_COL_ACTIONS'); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($this->items as $i => $item) : ?>
			<tr class="row<?php echo $i % 2; ?>">
				<td><?php echo $this->escape(LookupTitle::display((string) $item->award_title)); ?></td>
				<td>
					<?php echo $this->escape((string) $item->member_name); ?>
					<?php if (!empty($item->member_number)) : ?>
						<span class="text-muted">#<?php echo $this->escape((string) $item->member_number); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php echo $item->period_key !== ''
						? $this->escape((string) $item->period_key)
						: Text::_('COM_WENDFOLD_MEMBERAWARDS_PERIOD_LIFETIME'); ?>
				</td>
				<td class="text-center"><?php echo (int) $item->tier; ?></td>
				<td><?php echo Text::_($stateLabels[(int) $item->state] ?? 'COM_WENDFOLD_MEMBERAWARDS_STATE_GRANTED'); ?></td>
				<td><?php echo Text::_($sourceLabels[(int) $item->grant_source] ?? 'COM_WENDFOLD_MEMBERAWARDS_SOURCE_AUTO'); ?></td>
				<td><?php echo HTMLHelper::_('date', (string) $item->earned_at, Text::_('DATE_FORMAT_LC4')); ?></td>
				<td>
					<form action="<?php echo Route::_('index.php?option=com_wendfold&task=memberawards.setPresented'); ?>"
						method="post" class="d-flex gap-1">
						<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
						<input type="date" name="presented_on" class="form-control form-control-sm"
							value="<?php echo $this->escape((string) ($item->presented_on ?? '')); ?>">
						<button type="submit" class="btn btn-sm btn-outline-secondary">
							<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_BTN_SAVE_PRESENTED'); ?>
						</button>
						<?php echo HTMLHelper::_('form.token'); ?>
					</form>
				</td>
				<td>
					<?php if ((int) $item->is_locked === 1) : ?>
						<form action="<?php echo Route::_('index.php?option=com_wendfold&task=memberawards.unlock'); ?>"
							method="post" class="d-inline">
							<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
							<button type="submit" class="btn btn-sm btn-outline-warning"
								data-confirm="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_MEMBERAWARDS_CONFIRM_UNLOCK')); ?>">
								<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_BTN_UNLOCK'); ?>
							</button>
							<?php echo HTMLHelper::_('form.token'); ?>
						</form>
					<?php else : ?>
						<form action="<?php echo Route::_('index.php?option=com_wendfold&task=memberawards.lock'); ?>"
							method="post" class="d-inline">
							<input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
							<button type="submit" class="btn btn-sm btn-outline-secondary"
								data-confirm="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_MEMBERAWARDS_CONFIRM_LOCK')); ?>">
								<?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_BTN_LOCK'); ?>
							</button>
							<?php echo HTMLHelper::_('form.token'); ?>
						</form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php echo $this->pagination->getListFooter(); ?>
<?php endif; ?>

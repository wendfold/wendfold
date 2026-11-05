<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin template: Members List
 * admin/tmpl/members/default.php
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;
use Wendfold\Component\Wendfold\Administrator\Helper\MemberIdLabel;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

require dirname(__DIR__) . '/_includes/sortable_list_behavior.php';

$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'u.name'));
$listDirn  = $this->escape((string) StateValue::get($this->state, 'list.direction', 'ASC'));
$user = Factory::getApplication()->getIdentity();

$memberIdLabel = MemberIdLabel::forListHeader(
	(string) ComponentHelper::getParams('com_wendfold')->get('member_id_label', ''),
	Text::_('COM_WENDFOLD_MEMBER_NUMBER')
);
$memberIdSortTitle = MemberIdLabel::gridSortTitle($memberIdLabel);
?>

<div class="container-fluid">
	<form action="<?php echo Route::_('index.php?option=com_wendfold&view=members'); ?>"
		  method="post"
		  name="adminForm"
		  id="adminForm">

		<div class="row mb-3 align-items-center">
			<div class="col">
				<h2 class="mb-0"><?php echo Text::_('COM_WENDFOLD_MEMBERS_TITLE'); ?></h2>
			</div>
			<div class="col-auto">
				<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=member&layout=edit', false), ENT_QUOTES, 'UTF-8'); ?>"
				   class="btn btn-primary">
					<span class="icon-plus" aria-hidden="true"></span>
					<?php echo Text::_('COM_WENDFOLD_MEMBER_ADD'); ?>
				</a>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-header bg-light">
				<strong><?php echo Text::_('JSEARCH_FILTER'); ?></strong>
			</div>
			<div class="card-body">
				<div class="row g-2 align-items-end">
					<div class="col-md-4">
						<label class="visually-hidden" for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
						<div class="input-group">
							<input type="text"
								   name="filter_search"
								   id="filter_search"
								   class="form-control"
								   placeholder="<?php echo Text::_('COM_WENDFOLD_MEMBERS_SEARCH_PLACEHOLDER'); ?>"
								   value="<?php echo htmlspecialchars((string) StateValue::get($this->state, 'filter.search', ''), ENT_QUOTES, 'UTF-8'); ?>">
							<button type="submit" class="btn btn-outline-primary">
								<span class="icon-search" aria-hidden="true"></span>
							</button>
						</div>
					</div>

					<div class="col-md-3">
						<label class="visually-hidden" for="filter_renewal_status"><?php echo Text::_('COM_WENDFOLD_COL_RENEWAL_STATUS'); ?></label>
						<select name="filter_renewal_status"
								id="filter_renewal_status"
								class="form-select"
								onchange="this.form.submit()">
							<option value=""><?php echo Text::_('COM_WENDFOLD_FILTER_ALL_STATUSES'); ?></option>
							<option value="current" <?php echo in_array((string) StateValue::get($this->state, 'filter.renewal_status'), ['current', 'verified'], true) ? 'selected' : ''; ?>>
								<?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_CURRENT'); ?>
							</option>
							<option value="expiring" <?php echo ((string) StateValue::get($this->state, 'filter.renewal_status') === 'expiring') ? 'selected' : ''; ?>>
								<?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_EXPIRING'); ?>
							</option>
							<option value="expired" <?php echo ((string) StateValue::get($this->state, 'filter.renewal_status') === 'expired') ? 'selected' : ''; ?>>
								<?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_EXPIRED'); ?>
							</option>
							<option value="no_record" <?php echo in_array((string) StateValue::get($this->state, 'filter.renewal_status'), ['no_record', 'no_date'], true) ? 'selected' : ''; ?>>
								<?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_NO_RECORD'); ?>
							</option>
						</select>
					</div>

					<div class="col-md-2">
						<select name="filter_published"
								id="filter_published"
								class="form-select"
								onchange="this.form.submit()">
							<option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
							<option value="1" <?php echo ((string) StateValue::get($this->state, 'filter.published') === '1') ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
							<option value="0" <?php echo ((string) StateValue::get($this->state, 'filter.published') === '0') ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
						</select>
					</div>

					<div class="col-md-auto">
						<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=members', false), ENT_QUOTES, 'UTF-8'); ?>"
						   class="btn btn-outline-primary">
							<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
				<strong><?php echo Text::_('COM_WENDFOLD_MEMBERS_LIST_HEADING'); ?></strong>
				<small><?php echo (int) ($this->pagination->total ?? count($this->items)); ?> <?php echo Text::_('COM_WENDFOLD_MEMBERS_COUNT_LABEL'); ?></small>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-striped table-hover mb-0">
						<thead class="table-light">
							<tr>
								<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_NAME', 'u.name', $listDirn, $listOrder); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', $memberIdSortTitle, 'p.member_number', $listDirn, $listOrder); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_MEMBERSHIP_TYPE', 'mt.title', $listDirn, $listOrder); ?></th>
								<th><?php echo Text::_('COM_WENDFOLD_COL_RENEWAL_STATUS'); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_RENEWAL_DATE', 'p.renewal_date', $listDirn, $listOrder); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_DUES_PAID', 'p.dues_paid', $listDirn, $listOrder); ?></th>
								<th class="text-center"><?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'p.published', $listDirn, $listOrder); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if (empty($this->items)) : ?>
							<tr>
								<td colspan="8" class="text-center text-muted py-4">
									<?php echo Text::_('COM_WENDFOLD_MEMBERS_NO_MEMBERS'); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php $today = SiteTime::fromApplication()->today(); ?>
							<?php foreach ($this->items as $i => $item) : ?>
							<?php
							$membershipLabel = \Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) ($item->membership_type_title ?? '')); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							$renewalDate    = !empty($item->renewal_date) ? (string) $item->renewal_date : '';
							$isCurrent      = !empty($renewalDate) && (strtotime($renewalDate) >= strtotime($today));
							$isExpiring     = !empty($renewalDate) && !$isCurrent && false;

							if (!empty($renewalDate)) {
								$daysUntil = (int) floor((strtotime($renewalDate) - strtotime($today)) / 86400);
								$isExpiring = $daysUntil >= 0 && $daysUntil <= 60;
							}
							?>
							<tr>
								<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
								<td>
									<?php echo CheckoutAcl::checkedOutIcon($i, $item, 'members.', $user); ?>
									<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=member&layout=edit&id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>">
										<?php echo htmlspecialchars((string) ($item->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</a>
									<div class="small text-muted">
										<?php echo htmlspecialchars((string) ($item->email ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</div>
								</td>
								<td><?php echo htmlspecialchars((string) ($item->member_number ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
								<td>
									<?php
									if ($membershipLabel !== '') {
										echo '<span class="badge bg-primary">' . htmlspecialchars($membershipLabel, ENT_QUOTES, 'UTF-8') . '</span>';
									} else {
										echo '<span class="badge bg-secondary">' . Text::_('JNONE') . '</span>';
									}
									?>
								</td>
								<td>
									<?php if (!empty($renewalDate) && $isCurrent && !empty($item->national_verified)) : ?>
										<span class="badge bg-success"><?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_CURRENT'); ?></span>
									<?php elseif (!empty($renewalDate) && $isExpiring) : ?>
										<span class="badge bg-warning text-dark"><?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_EXPIRING'); ?></span>
									<?php elseif (!empty($renewalDate)) : ?>
										<span class="badge bg-danger"><?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_EXPIRED'); ?></span>
									<?php else : ?>
										<span class="badge bg-secondary"><?php echo Text::_('COM_WENDFOLD_RENEWAL_STATUS_NO_RECORD'); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php echo !empty($renewalDate) ? htmlspecialchars(DateOnly::format($renewalDate, 'M j, Y'), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">-</span>'; ?>
								</td>
								<td>
									<?php if (!empty($item->dues_paid)) : ?>
										<span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
									<?php else : ?>
										<span class="badge bg-secondary"><?php echo Text::_('JNO'); ?></span>
									<?php endif; ?>
								</td>
								<td class="text-center">
									<?php if (!empty($item->published)) : ?>
										<span class="badge bg-success"><?php echo Text::_('JPUBLISHED'); ?></span>
									<?php else : ?>
										<span class="badge bg-secondary"><?php echo Text::_('JUNPUBLISHED'); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<?php if (!empty($this->pagination)) : ?>
		<div class="mt-3">
			<?php echo $this->pagination->getListFooter(); ?>
		</div>
		<?php endif; ?>

		<input type="hidden" name="task" value="">
		<input type="hidden" name="boxchecked" value="0">
		<input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>">
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>

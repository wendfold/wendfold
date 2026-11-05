<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin template: Officers List
 * admin/tmpl/officers/default.php
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

require dirname(__DIR__) . '/_includes/sortable_list_behavior.php';

$wlParams     = ComponentHelper::getParams('com_wendfold');
$chapterLabel = (string) $wlParams->get('chapter_label', 'Chapter');

$user      = Factory::getApplication()->getIdentity();
$canCreate = $user->authorise('core.create', 'com_wendfold');
$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'o.is_current'));
$listDirn  = $this->escape((string) StateValue::get($this->state, 'list.direction', 'DESC'));
?>

<div class="container-fluid">
	<form action="<?php echo Route::_('index.php?option=com_wendfold&view=officers'); ?>"
		  method="post"
		  name="adminForm"
		  id="adminForm">

		<div class="row mb-3 align-items-center">
			<div class="col">
				<h2 class="mb-0"><?php echo htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_WENDFOLD_OFFICERS_TITLE_SUFFIX'); ?></h2>
			</div>
			<?php if ($canCreate) : ?>
			<div class="col-auto">
				<a class="btn btn-primary" href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&task=officer.add', false), ENT_QUOTES, 'UTF-8'); ?>">
					<span class="icon-plus" aria-hidden="true"></span>
					<?php echo Text::_('JACTION_CREATE'); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>

		<div class="card">
			<div class="card-header bg-primary text-white">
				<strong><?php echo htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_WENDFOLD_OFFICERS_ROSTER_HEADING_SUFFIX'); ?></strong>
			</div>
			<div class="card-body <?php echo empty($this->items) ? '' : 'p-0'; ?>">
				<?php if (empty($this->items)) : ?>
					<div class="text-center py-5">
						<div class="mb-3">
							<span class="icon-user" aria-hidden="true" style="font-size:4rem"></span>
						</div>
						<h2 class="mb-2"><?php echo Text::_('COM_WENDFOLD_EMPTY_OFFICERS_TITLE'); ?></h2>
						<p class="text-muted mb-4"><?php echo Text::_('COM_WENDFOLD_EMPTY_OFFICERS_DESC'); ?></p>
						<?php if ($canCreate) : ?>
							<a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_wendfold&task=officer.add'); ?>">
								<span class="icon-plus" aria-hidden="true"></span>
								<?php echo Text::_('COM_WENDFOLD_EMPTY_OFFICERS_BUTTON'); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php else : ?>
				<div class="table-responsive">
					<table class="table table-striped table-hover mb-0">
						<thead class="table-light">
							<tr>
								<th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_NAME', 'u.name', $listDirn, $listOrder); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_ROLE', 'o.role_order', $listDirn, $listOrder); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_TERM_START', 'o.term_start', $listDirn, $listOrder); ?></th>
								<th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_TERM_END', 'o.term_end', $listDirn, $listOrder); ?></th>
								<th class="text-center"><?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'o.published', $listDirn, $listOrder); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($this->items as $i => $item) : ?>
							<tr>
								<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
								<td>
									<?php echo CheckoutAcl::checkedOutIcon($i, $item, 'officers.', $user); ?>
									<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=officer&layout=edit&id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>">
										<?php
										$displayName = trim((string) ($item->name ?? ''));
										echo $displayName !== ''
											? htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8')
											: '<span class="text-muted">-</span>';
										?>
									</a>
								</td>
								<td>
									<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=officer&layout=edit&id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>">
										<?php echo htmlspecialchars(\Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) ($item->role_title ?? '')), ENT_QUOTES, 'UTF-8'); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps ?>
									</a>
								</td>
								<td><?php echo !empty($item->term_start) ? htmlspecialchars(DateOnly::format($item->term_start, 'M j, Y'), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">-</span>'; ?></td>
								<td><?php echo !empty($item->term_end) ? htmlspecialchars(DateOnly::format($item->term_end, 'M j, Y'), ENT_QUOTES, 'UTF-8') : Text::_('COM_WENDFOLD_OFFICERS_PRESENT'); ?></td>
								<td class="text-center">
									<?php if (!empty($item->published)) : ?>
										<span class="badge bg-success"><?php echo Text::_('JPUBLISHED'); ?></span>
									<?php else : ?>
										<span class="badge bg-secondary"><?php echo Text::_('JUNPUBLISHED'); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
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

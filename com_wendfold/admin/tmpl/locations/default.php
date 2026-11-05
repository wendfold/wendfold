<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

require dirname(__DIR__) . '/_includes/sortable_list_behavior.php';

$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'l.name'));
$listDirn  = $this->escape((string) StateValue::get($this->state, 'list.direction', 'ASC'));

$filterSearch = trim((string) StateValue::get($this->state, 'filter.search', ''));
$filterType   = (string) StateValue::get($this->state, 'filter.location_type', '');
$hasFilters   = ($filterSearch !== '') || ($filterType !== '');

$user = Factory::getApplication()->getIdentity();
$canCreate = $user->authorise('core.create', 'com_wendfold');

$typeLabels = [
    1 => ['label' => Text::_('COM_WENDFOLD_LOCATION_TYPE_STOP'),  'class' => 'bg-primary'],
    2 => ['label' => Text::_('COM_WENDFOLD_LOCATION_TYPE_VENUE'), 'class' => 'bg-success'],
    3 => ['label' => Text::_('COM_WENDFOLD_LOCATION_TYPE_BOTH'),  'class' => 'bg-warning text-dark'],
];
?>
<form action="<?php echo Route::_('index.php?option=com_wendfold&view=locations'); ?>"
      method="post" name="adminForm" id="adminForm">

    <?php if (!empty($this->items) || $hasFilters) : ?>
    <div class="row mb-3">
        <div class="col-md-6 d-flex gap-2">
            <input type="text" name="filter_search" id="filter_search" class="form-control"
                   placeholder="<?php echo Text::_('COM_WENDFOLD_LOCATIONS_SEARCH_PLACEHOLDER'); ?>"
                   value="<?php echo htmlspecialchars(StateValue::get($this->state, 'filter.search', ''), ENT_QUOTES); ?>">
            <button type="submit" class="btn btn-primary">
                <span class="icon-search"></span> <?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>
            </button>
            <button type="button" class="btn btn-secondary"
                    onclick="document.getElementById('filter_search').value='';this.form.submit();">
                <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
            </button>
        </div>
        <div class="col-md-3">
            <select name="filter_location_type" class="form-select" onchange="this.form.submit()">
                <option value=""><?php echo Text::_('COM_WENDFOLD_FILTER_ALL_TYPES'); ?></option>
                <option value="1" <?php echo StateValue::get($this->state, 'filter.location_type') === '1' ? 'selected' : ''; ?>>
                    <?php echo Text::_('COM_WENDFOLD_LOCATION_TYPE_STOP'); ?>
                </option>
                <option value="2" <?php echo StateValue::get($this->state, 'filter.location_type') === '2' ? 'selected' : ''; ?>>
                    <?php echo Text::_('COM_WENDFOLD_LOCATION_TYPE_VENUE'); ?>
                </option>
                <option value="3" <?php echo StateValue::get($this->state, 'filter.location_type') === '3' ? 'selected' : ''; ?>>
                    <?php echo Text::_('COM_WENDFOLD_LOCATION_TYPE_BOTH'); ?>
                </option>
            </select>
        </div>
    </div>

    <div class="mb-2 text-muted small"><?php echo count($this->items); ?> <?php echo Text::_('COM_WENDFOLD_LOCATIONS_COUNT'); ?></div>

    <?php endif; ?>

    <?php if (empty($this->items) && !$hasFilters) : ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <span class="icon-map" aria-hidden="true" style="font-size:4rem"></span>
            </div>
            <h2 class="mb-2"><?php echo Text::_('COM_WENDFOLD_EMPTY_LOCATIONS_TITLE'); ?></h2>
            <p class="text-muted mb-4"><?php echo Text::_('COM_WENDFOLD_EMPTY_LOCATIONS_DESC'); ?></p>
            <?php if ($canCreate) : ?>
                <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_wendfold&task=location.add'); ?>">
                    <span class="icon-plus" aria-hidden="true"></span>
                    <?php echo Text::_('COM_WENDFOLD_EMPTY_LOCATIONS_BUTTON'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else : ?>

    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                <th><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_NAME', 'l.name', $listDirn, $listOrder); ?></th>
                <th style="width:120px"><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_TYPE', 'l.location_type', $listDirn, $listOrder); ?></th>
                <th><?php echo Text::_('COM_WENDFOLD_COL_ADDRESS'); ?></th>
                <th style="width:60px" class="text-center"><?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_DEFAULT', 'l.is_default', $listDirn, $listOrder); ?></th>
                <th style="width:70px" class="text-center"><?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'l.published', $listDirn, $listOrder); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)) : ?>
            <tr>
                <td colspan="6" class="text-center p-4 text-muted">
                    <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ($this->items as $i => $item) :
                $type = $typeLabels[$item->location_type] ?? ['label' => '?', 'class' => 'bg-secondary'];
                $addr = trim(($item->city ?? '') . ($item->state ? ', ' . $item->state : ''));
            ?>
            <tr>
                <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                <td>
					<?php echo CheckoutAcl::checkedOutIcon($i, $item, 'locations.', $user); ?>
                    <a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&task=location.edit&id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item->name, ENT_QUOTES); ?>
                    </a>
                    <?php if ($item->notes) : ?>
                        <div class="small text-muted"><?php echo htmlspecialchars($item->notes, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $type['label']; ?></span></td>
                <td><?php echo htmlspecialchars($addr ?: ' - ', ENT_QUOTES); ?></td>
                <td class="text-center">
                    <?php if ($item->is_default) : ?>
                        <span class="icon-star text-warning" title="<?php echo Text::_('COM_WENDFOLD_COL_DEFAULT'); ?>"></span>
                    <?php else : ?>
                        <span class="text-muted"> - </span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($item->published) : ?>
                        <span class="icon-check-circle text-success"></span>
                    <?php else : ?>
                        <span class="icon-times-circle text-danger"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php endif; ?>

    <?php echo $this->pagination->getListFooter(); ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

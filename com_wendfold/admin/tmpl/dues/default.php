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
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

require dirname(__DIR__) . '/_includes/sortable_list_behavior.php';

$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'd.payment_date'));
$listDirn  = $this->escape((string) StateValue::get($this->state, 'list.direction', 'DESC'));
$siteYear     = SiteTime::fromApplication()->year();
$filterYear = StateValue::get($this->state, 'filter.year', (string) $siteYear);

$filterSearch = trim((string) StateValue::get($this->state, 'filter.search', ''));
$defaultYear  = $siteYear;
$hasFilters   = ($filterSearch !== '') || ((int) $filterYear !== $defaultYear);

$user = Factory::getApplication()->getIdentity();
$canCreate = $user->authorise('core.create', 'com_wendfold');
?>

<form action="<?php echo Route::_('index.php?option=com_wendfold&view=dues'); ?>"
      method="post" name="adminForm" id="adminForm">

    <?php if (!empty($this->items) || $hasFilters) : ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="input-group">
                <input type="text" name="filter_search" id="filter_search"
                       placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
                       value="<?php echo $this->escape(StateValue::get($this->state, 'filter.search')); ?>"
                       class="form-control">
                <button type="submit" class="btn btn-primary">
                    <span class="icon-search"></span>
                </button>
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('filter_search').value='';this.form.submit();">
                    <?php echo Text::_('JCLEAR'); ?>
                </button>
            </div>
        </div>
        <div class="col-md-2">
            <select name="filter_year" class="form-select" onchange="this.form.submit();">
                <?php for ($y = $siteYear + 1; $y >= 2020; $y--) : ?>
                <option value="<?php echo htmlspecialchars((string) $y, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($y == $filterYear) ? 'selected' : ''; ?>>
                    <?php echo $y; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($this->items) && !$hasFilters) : ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <span class="icon-credit-card" aria-hidden="true" style="font-size:4rem"></span>
            </div>
            <h2 class="mb-2"><?php echo Text::_('COM_WENDFOLD_EMPTY_DUES_TITLE'); ?></h2>
            <p class="text-muted mb-4"><?php echo Text::_('COM_WENDFOLD_EMPTY_DUES_DESC'); ?></p>
            <?php if ($canCreate) : ?>
                <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_wendfold&task=duespayment.add'); ?>">
                    <span class="icon-plus" aria-hidden="true"></span>
                    <?php echo Text::_('COM_WENDFOLD_EMPTY_DUES_BUTTON'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else : ?>

    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th class="w-1 text-center">
                    <input type="checkbox" name="checkall-toggle" value=""
                           onclick="Joomla.checkAll(this)">
                </th>
                <th>
                    <?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_MEMBER', 'member_name', $listDirn, $listOrder); ?>
                </th>
                <th style="width:80px">
                    <?php echo Text::_('COM_WENDFOLD_COL_MEMBER_NUMBER'); ?>
                </th>
                <th style="width:60px">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_YEAR', 'd.year', $listDirn, $listOrder); ?>
                </th>
                <th class="text-end" style="width:90px">
                    <?php echo Text::_('COM_WENDFOLD_COL_AMOUNT'); ?>
                </th>
                <th style="width:110px">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_PAYMENT_DATE', 'd.payment_date', $listDirn, $listOrder); ?>
                </th>
                <th style="width:90px">
                    <?php echo Text::_('COM_WENDFOLD_COL_METHOD'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)) : ?>
            <tr>
                <td colspan="7" class="text-center p-4 text-muted">
                    <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ($this->items as $i => $item) : ?>
            <tr>
                <td class="text-center">
                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                </td>
                <td>
					<?php echo CheckoutAcl::checkedOutIcon($i, $item, 'dues.', $user); ?>
                    <a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&task=duespayment.edit&id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo $this->escape($item->member_name); ?>
                    </a>
                </td>
                <td class="small text-muted"><?php echo $this->escape($item->member_number ?? ' - '); ?></td>
                <td><strong><?php echo (int) $item->year; ?></strong></td>
                <td class="text-end"><?php echo number_format($item->amount_paid, 2); ?></td>
                <td class="small"><?php echo $item->payment_date ? DateOnly::format($item->payment_date, 'M j, Y') : ' - '; ?></td>
                <td class="small"><?php echo $this->escape($item->payment_method); ?></td>
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

<?php
/**
 * Shared admin list behavior for Wendfold.
 *
 * Loads Joomla core list behavior for custom administrator list tables.
 * These views use standard hidden ordering inputs (filter_order and
 * filter_order_Dir) with Joomla grid.sort() headers.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('behavior.core');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

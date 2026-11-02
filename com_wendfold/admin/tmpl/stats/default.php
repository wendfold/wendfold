<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin template: Member Statistics and Leaderboards
 * admin/tmpl/stats/default.php
 *
 * Fix: Replaced Joomla 4-style HTMLHelper::_('bootstrap.startTabSet') /
 * addTab / endTab / endTabSet helpers with Bootstrap 5 native markup.
 * Those helpers do not exist in Joomla 6  -  using them causes the tab nav
 * to render but panels never switch.
 *
 * Tab pattern used (consistent with Sync and Memberawards views):
 *   <a href="#panel-id" data-bs-toggle="tab"> with matching <div id="panel-id">
 * HTMLHelper::_('bootstrap.tab') is called in HtmlView::display().
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

require dirname(__DIR__) . '/_includes/sortable_list_behavior.php';

$listOrder  = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'total_miles_ytd'));
$listDirn   = $this->escape((string) StateValue::get($this->state, 'list.direction', 'DESC'));
?>

<form action="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_wendfold&view=stats'); ?>"
      method="post" name="adminForm" id="adminForm">

    <!-- =====================================================
         Chapter Summary Card
    ===================================================== -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <strong><?php echo Text::_('COM_WENDFOLD_STATS_CHAPTER_SUMMARY'); ?></strong>
                </div>
                <div class="card-body">
                    <?php if (!empty($this->chapterSummary)) : ?>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3">
                                <div class="display-4 text-primary">
                                    <?php echo number_format((float) ($this->chapterSummary->total_miles ?? 0), 1); ?>
                                </div>
                                <div class="text-muted"><?php echo Text::_('COM_WENDFOLD_STATS_TOTAL_MILES'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <div class="display-4 text-primary">
                                    <?php echo number_format((int) ($this->chapterSummary->total_participations ?? 0)); ?>
                                </div>
                                <div class="text-muted"><?php echo Text::_('COM_WENDFOLD_STATS_TOTAL_PARTICIPATIONS'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <div class="display-4 text-warning">
                                    <?php echo number_format((int) ($this->chapterSummary->total_members ?? 0)); ?>
                                </div>
                                <div class="text-muted"><?php echo Text::_('COM_WENDFOLD_STATS_ACTIVE_MEMBERS'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================
         Awards (definitions and member grants)
    ===================================================== -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <strong><?php echo Text::_('COM_WENDFOLD_STATS_ACHIEVEMENTS_TITLE'); ?></strong>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <?php echo Text::_('COM_WENDFOLD_STATS_ACHIEVEMENTS_DESC'); ?>
                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_wendfold&view=awarddefinitions'); ?>">
                            <span class="icon-star" aria-hidden="true"></span>
                            <?php echo Text::_('COM_WENDFOLD_AWARDS_TITLE'); ?>
                        </a>

                        <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_wendfold&view=memberawards'); ?>">
                            <span class="icon-trophy" aria-hidden="true"></span>
                            <?php echo Text::_('COM_WENDFOLD_MEMBERAWARDS_TITLE'); ?>
                        </a>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================
         Search Box
    ===================================================== -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text"
                       name="filter_search"
                       placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
                       value="<?php echo $this->escape(StateValue::get($this->state, 'filter.search')); ?>"
                       class="form-control" />
                <button type="submit" class="btn btn-primary">
                    <span class="icon-search" aria-hidden="true"></span>
                </button>
                <button type="button"
                        class="btn btn-secondary"
                        onclick="document.querySelector('[name=filter_search]').value='';this.form.submit();">
                    <span class="icon-times" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- =====================================================
         Bootstrap 5 Tab Navigation
         Use <a href="#id" data-bs-toggle="tab">  -  reliable
         in Joomla 6's Bootstrap asset pipeline.
         DO NOT use HTMLHelper::_('bootstrap.startTabSet')  - 
         that helper does not exist in Joomla 6.
    ===================================================== -->
    <ul class="nav nav-tabs mb-3" id="statsTabs" role="tablist">

        <li class="nav-item" role="presentation">
            <a class="nav-link active"
               id="stats-miles-tab"
               href="#stats-miles"
               data-bs-toggle="tab"
               role="tab"
               aria-controls="stats-miles"
               aria-selected="true">
                <?php echo Text::_('COM_WENDFOLD_STATS_TOP_MILES'); ?>
            </a>
        </li>

        <li class="nav-item" role="presentation">
            <a class="nav-link"
               id="stats-active-tab"
               href="#stats-active"
               data-bs-toggle="tab"
               role="tab"
               aria-controls="stats-active"
               aria-selected="false">
                <?php echo Text::_('COM_WENDFOLD_STATS_TOP_ATTENDANCE'); ?>
            </a>
        </li>

        <li class="nav-item" role="presentation">
            <a class="nav-link"
               id="stats-all-tab"
               href="#stats-all"
               data-bs-toggle="tab"
               role="tab"
               aria-controls="stats-all"
               aria-selected="false">
                <?php echo Text::_('COM_WENDFOLD_STATS_ALL_MEMBERS'); ?>
            </a>
        </li>

    </ul>

    <!-- =====================================================
         Tab Panes  -  each id must match the href="#..." above
    ===================================================== -->
    <div class="tab-content" id="statsTabContent">

        <!-- ==================================================
             TAB 1: Top Miles Leaderboard
        ================================================== -->
        <div class="tab-pane fade show active"
             id="stats-miles"
             role="tabpanel"
             aria-labelledby="stats-miles-tab">

            <?php if (empty($this->topMiles)) : ?>
                <div class="alert alert-info">
                    <?php echo Text::_('COM_WENDFOLD_STATS_NO_DATA'); ?>
                </div>
            <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="width:5%"><?php echo Text::_('COM_WENDFOLD_STATS_RANK'); ?></th>
                            <th><?php echo Text::_('COM_WENDFOLD_STATS_MEMBER'); ?></th>
                            <th style="width:15%"><?php echo Text::_('COM_WENDFOLD_STATS_MEMBER_NUMBER'); ?></th>
                            <th style="width:15%" class="text-end"><?php echo Text::_('COM_WENDFOLD_STATS_MILES'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        foreach ($this->topMiles as $member) :
                        ?>
                        <tr>
                            <td>
                                <?php if ($rank === 1) : ?>
                                    <span class="badge bg-warning text-dark">1</span>
                                <?php elseif ($rank === 2) : ?>
                                    <span class="badge bg-secondary">2</span>
                                <?php elseif ($rank === 3) : ?>
                                    <span class="badge bg-danger">3</span>
                                <?php else : ?>
                                    <?php echo $rank; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $this->escape($member->member_name); ?></td>
                            <td><?php echo $this->escape($member->member_number); ?></td>
                            <td class="text-end">
                                <strong><?php echo number_format((float) $member->total_miles_ytd, 1); ?></strong> mi
                            </td>
                        </tr>
                        <?php
                        $rank++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div><!-- #stats-miles -->

        <!-- ==================================================
             TAB 2: Most Active (Attendance) Leaderboard
        ================================================== -->
        <div class="tab-pane fade"
             id="stats-active"
             role="tabpanel"
             aria-labelledby="stats-active-tab">

            <?php if (empty($this->topAttendance)) : ?>
                <div class="alert alert-info">
                    <?php echo Text::_('COM_WENDFOLD_STATS_NO_DATA'); ?>
                </div>
            <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="width:5%"><?php echo Text::_('COM_WENDFOLD_STATS_RANK'); ?></th>
                            <th><?php echo Text::_('COM_WENDFOLD_STATS_MEMBER'); ?></th>
                            <th style="width:15%"><?php echo Text::_('COM_WENDFOLD_STATS_MEMBER_NUMBER'); ?></th>
                            <th style="width:15%" class="text-end"><?php echo Text::_('COM_WENDFOLD_STATS_EVENTS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        foreach ($this->topAttendance as $member) :
                        ?>
                        <tr>
                            <td>
                                <?php if ($rank === 1) : ?>
                                    <span class="badge bg-warning text-dark">1</span>
                                <?php elseif ($rank === 2) : ?>
                                    <span class="badge bg-secondary">2</span>
                                <?php elseif ($rank === 3) : ?>
                                    <span class="badge bg-danger">3</span>
                                <?php else : ?>
                                    <?php echo $rank; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $this->escape($member->member_name); ?></td>
                            <td><?php echo $this->escape($member->member_number); ?></td>
                            <td class="text-end">
                                <strong><?php echo number_format((int) $member->events_attended_ytd); ?></strong> events
                            </td>
                        </tr>
                        <?php
                        $rank++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div><!-- #stats-active -->

        <!-- ==================================================
             TAB 3: All Members (paginated)
        ================================================== -->
        <div class="tab-pane fade"
             id="stats-all"
             role="tabpanel"
             aria-labelledby="stats-all-tab">

            <?php if (empty($this->items)) : ?>
                <div class="alert alert-info">
                    <span class="icon-info-circle" aria-hidden="true"></span>
                    <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                </div>
            <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped" id="memberList">
                    <thead>
                        <tr>
                            <th>
                                <?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_STATS_MEMBER', 'member_name', $listDirn, $listOrder); ?>
                            </th>
                            <th style="width:12%">
                                <?php echo Text::_('COM_WENDFOLD_STATS_MEMBER_NUMBER'); ?>
                            </th>
                            <th style="width:12%" class="text-end">
                                <?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_STATS_MILES', 'total_miles_ytd', $listDirn, $listOrder); ?>
                            </th>
                            <th style="width:12%" class="text-end">
                                <?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_STATS_EVENTS', 'events_attended_ytd', $listDirn, $listOrder); ?>
                            </th>
                            <th style="width:12%">
                                <?php echo Text::_('COM_WENDFOLD_STATS_LAST_ACTIVITY'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->items as $item) : ?>
                        <tr>
                            <td><?php echo $this->escape($item->member_name); ?></td>
                            <td><?php echo $this->escape($item->member_number); ?></td>
                            <td class="text-end">
                                <strong><?php echo number_format((float) $item->total_miles_ytd, 1); ?></strong> mi
                            </td>
                            <td class="text-end">
                                <strong><?php echo number_format((int) $item->events_attended_ytd); ?></strong>
                            </td>
                            <td>
                                <?php $activityDay = (string) ($item->last_entry_date ?? ''); echo $activityDay !== '' ? $this->escape(DateOnly::format($activityDay, 'M d, Y')) : '<span class="text-muted">-</span>'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php echo $this->pagination->getListFooter(); ?>
            <?php endif; ?>

        </div><!-- #stats-all -->

    </div><!-- .tab-content -->

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>

</form>

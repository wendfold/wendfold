<?php
/**
 * Wendfold - Chapter Status Report Template
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

$members = $this->members;
?>

<div class="container-fluid">

    <!-- Page heading & export button -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_TITLE'); ?></h2>
            <p class="text-muted mb-0 small"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_DESC'); ?></p>
        </div>
        <form action="<?php echo Route::_('index.php?option=com_wendfold&task=statusreport.exportcsv'); ?>" method="post">
            <?php echo HTMLHelper::_('form.token'); ?>
            <button type="submit" class="btn btn-outline-success btn-sm">
                <span class="icon-download me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_WENDFOLD_BTN_EXPORT_CSV'); ?>
            </button>
        </form>
    </div>

    <!-- Summary cards -->
    <?php if (!empty($this->summary)) : $s = $this->summary; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-danger"><?php echo (int) $s->expired; ?></div>
                    <div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_EXPIRED'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-warning"><?php echo (int) $s->expiring_soon; ?></div>
                    <div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_EXPIRING_SOON'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-success"><?php echo (int) $s->verified; ?></div>
                    <div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_VERIFIED'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-secondary">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-secondary"><?php echo (int) $s->total; ?></div>
                    <div class="small text-muted"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_TOTAL'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($members)) : ?>
        <div class="alert alert-success">
            <?php echo Text::_('COM_WENDFOLD_STATUSREPORT_NO_EXPIRING'); ?>
        </div>
    <?php else : ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th><?php echo Text::_('COM_WENDFOLD_COL_NAME'); ?></th>
                        <th style="width:100px"><?php echo Text::_('COM_WENDFOLD_COL_MEMBER_NUMBER'); ?></th>
                        <th style="width:110px"><?php echo Text::_('COM_WENDFOLD_COL_TYPE'); ?></th>
                        <th style="width:120px"><?php echo Text::_('COM_WENDFOLD_COL_RENEWAL'); ?></th>
                        <th class="text-center" style="width:90px"><?php echo Text::_('COM_WENDFOLD_COL_DAYS'); ?></th>
                        <th class="text-center" style="width:80px"><?php echo Text::_('COM_WENDFOLD_COL_VERIFIED'); ?></th>
                        <th class="text-center" style="width:110px"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_COL_REM60'); ?></th>
                        <th class="text-center" style="width:110px"><?php echo Text::_('COM_WENDFOLD_STATUSREPORT_COL_REM30'); ?></th>
                        <th style="width:70px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $siteTime = SiteTime::fromApplication();
                    foreach ($members as $member) :
                        $cue       = $siteTime->renewalCue((string) ($member->renewal_date ?? ''));
                        $isPast    = $cue['past'];
                        $rowClass  = $cue['rowClass'];
                        $daysLabel = $cue['label'];

                        $editUrl = Route::_(
                            'index.php?option=com_wendfold&task=member.edit&id=' . (int) $member->id,
                            false
                        );
                    ?>
                        <tr class="<?php echo htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8'); ?>">
                            <td>
                                <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>" class="fw-semibold">
                                    <?php echo $this->escape($member->member_name ?? '(No Name)'); ?>
                                </a>
                            </td>
                            <td><?php echo $this->escape($member->member_number); ?></td>
                            <td><?php echo $this->escape($member->membership_type_label); ?></td>
                            <td>
                                <?php echo $member->renewal_date
                                    ? DateOnly::format($member->renewal_date, 'M j, Y')
                                    : '<span class="text-muted"> - </span>'; ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold <?php echo htmlspecialchars($isPast ? 'text-danger' : 'text-warning', ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $daysLabel; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($member->national_verified) : ?>
                                    <span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-secondary"><?php echo Text::_('JNO'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($member->reminder_60_sent)) : ?>
                                    <span class="text-success" title="<?php echo $member->reminder_60_sent; ?>">
                                        <span class="icon-check-circle" aria-hidden="true"></span>
                                        <?php echo DateOnly::format($member->reminder_60_sent, 'M j'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="text-muted"> - </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($member->reminder_30_sent)) : ?>
                                    <span class="text-success" title="<?php echo $member->reminder_30_sent; ?>">
                                        <span class="icon-check-circle" aria-hidden="true"></span>
                                        <?php echo DateOnly::format($member->reminder_30_sent, 'M j'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="text-muted"> - </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">
                                    <?php echo Text::_('JACTION_EDIT'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <!-- Back link -->
    <div class="mt-3">
        <a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=sync', false), ENT_QUOTES, 'UTF-8'); ?>"
           class="btn btn-secondary">
            <?php echo Text::_('COM_WENDFOLD_BACK'); ?>
        </a>
    </div>

</div>

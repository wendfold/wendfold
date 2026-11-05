<?php

/**
 * Wendfold - My Activities layout (Site)
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricDisplayLabel;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricEntryTitle;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

/** @var \Wendfold\Component\Wendfold\Site\View\Myactivities\HtmlView $this */

$items = $this->items;
$totals = $this->totals;
$dashUrl = RouteHelper::dashboard();
$logUrl = Route::_('index.php?option=com_wendfold&view=activitylog');

$approvalLabel = static function (int $published): string {
	if ($published === 1)
	{
		return Text::_('COM_WENDFOLD_ACTIVITIES_COUNTED');
	}

	if ($published === -2)
	{
		return Text::_('COM_WENDFOLD_ACTIVITIES_REJECTED_STATE');
	}

	return Text::_('COM_WENDFOLD_ACTIVITIES_PENDING');
};

ob_start();
?>
<a class="<?php echo hcButtonClasses('back'); ?>" href="<?php echo htmlspecialchars($dashUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_BACK_DASHBOARD'); ?>
</a>
<a class="<?php echo hcButtonClasses('secondary'); ?>" href="<?php echo htmlspecialchars($logUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_ACTIVITY_LOG_LINK'); ?>
</a>
<?php
$headerActions = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_MYACTIVITIES_PAGE_TITLE'),
	'actionsHtml' => $headerActions,
];
?>
<div class="hc-page hc-page--myactivities hc-myactivities">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_TOTALS_HEADING'); ?></h2>
				</div>
			</div>
			<div class="hc-panel__body">
				<?php if ($totals === []) : ?>
					<p class="hc-myactivities-empty mb-0">
						<?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_TOTALS_EMPTY'); ?>
					</p>
				<?php else : ?>
					<ul class="hc-myactivities-totals list-unstyled mb-0">
						<?php foreach ($totals as $totalRow) : ?>
							<?php
							$decimals = max(0, min(3, (int) $totalRow->decimals));
							$amount = number_format((float) $totalRow->total, $decimals, '.', '');
							$title = (string) $totalRow->title;
							$valueText = MetricDisplayLabel::formatAmountWithUnit(
								$amount,
								(float) $totalRow->total,
								(string) ($totalRow->unit_label ?? ''),
								$title
							);
							?>
							<li>
								<?php echo $this->escape($title); ?>:
								<?php echo $this->escape($valueText); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>

		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_ENTRIES_HEADING'); ?></h2>
				</div>
			</div>
			<div class="hc-panel__body">
				<?php if ($items === []) : ?>
					<p class="hc-myactivities-empty mb-0">
						<?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_EMPTY'); ?>
					</p>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table table-striped hc-myactivities-table mb-0">
							<thead>
								<tr>
									<th scope="col"><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_DATE'); ?></th>
									<th scope="col"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_TITLE'); ?></th>
									<th scope="col"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_METRIC'); ?></th>
									<th scope="col"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_VALUE'); ?></th>
									<th scope="col"><?php echo Text::_('COM_WENDFOLD_ACTIVITY_NOTES'); ?></th>
									<th scope="col"><?php echo Text::_('COM_WENDFOLD_ACTIVITIES_APPROVAL'); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($items as $item) : ?>
								<?php
								$decimals = max(0, min(3, (int) $item->decimals));
								$amount = number_format((float) $item->value, $decimals, '.', '');
								$note = trim((string) $item->note);
								$metricTitle = LookupTitle::display((string) $item->metric_title);
								$activityTitle = MetricEntryTitle::display(
									isset($item->activity_title) ? (string) $item->activity_title : null
								);
								$valueText = MetricDisplayLabel::formatAmountWithUnit(
									$amount,
									(float) $item->value,
									(string) $item->unit_label,
									$metricTitle
								);
								?>
								<tr>
									<td><?php echo $this->escape(DateOnly::format((string) $item->entry_date, 'M d, Y')); ?></td>
									<td>
										<?php if ($activityTitle !== '') : ?>
											<?php echo $this->escape($activityTitle); ?>
										<?php else : ?>
											<span class="text-muted"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_NOTE_NONE'); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo $this->escape($metricTitle); ?></td>
									<td><?php echo $this->escape($valueText); ?></td>
									<td>
										<?php if ($note !== '') : ?>
											<?php echo $this->escape($note); ?>
										<?php else : ?>
											<span class="text-muted"><?php echo Text::_('COM_WENDFOLD_MYACTIVITIES_NOTE_NONE'); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo $this->escape($approvalLabel((int) $item->published)); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if (!empty($this->pagination)) : ?>
						<div class="hc-myactivities-pagination mt-3">
							<?php echo $this->pagination->getListFooter(); ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</section>
	</div>
</div>

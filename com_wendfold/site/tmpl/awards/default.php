<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

// Database column names are snake_case on award rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

/** @var \Wendfold\Component\Wendfold\Site\View\Awards\HtmlView $this */
$catalog = $this->catalog;
$profile = $this->profile;
$earned = $this->earned;
$isLoggedIn = (bool) $this->isLoggedIn;

$periodLabels = [
	1 => Text::_('COM_WENDFOLD_AWARD_PERIOD_LIFETIME'),
	2 => Text::_('COM_WENDFOLD_AWARD_PERIOD_CALENDAR_YEAR'),
	3 => Text::_('COM_WENDFOLD_AWARD_PERIOD_ROLLING'),
	4 => Text::_('COM_WENDFOLD_AWARD_PERIOD_CUSTOM'),
];

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_AWARDS_PAGE_TITLE'),
	'subtitle' => Text::_('COM_WENDFOLD_AWARDS_PAGE_SUBTITLE'),
	'actionsHtml' => '',
];
?>
<div class="hc-page hc-page--awards hc-awards">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_AWARDS_CATALOG_TITLE'); ?></h2>
				</div>
			</div>
			<div class="hc-panel__body">
				<?php if (empty($catalog)) : ?>
					<div class="alert alert-light border mb-0">
						<?php echo Text::_('COM_WENDFOLD_AWARDS_CATALOG_EMPTY'); ?>
					</div>
				<?php else : ?>
					<div class="wendfold-awards-grid">
						<?php foreach ($catalog as $definition) : ?>
							<article class="wendfold-award-card">
								<div class="wendfold-award-card__top">
									<div>
										<div class="wendfold-award-card__name">
											<?php echo htmlspecialchars(LookupTitle::display((string) $definition->title), ENT_QUOTES, 'UTF-8'); ?>
										</div>
										<?php if (!empty($definition->description)) : ?>
											<div class="wendfold-award-card__desc">
												<?php echo htmlspecialchars((string) $definition->description, ENT_QUOTES, 'UTF-8'); ?>
											</div>
										<?php endif; ?>
									</div>
									<span class="hc-badge hc-badge-neutral">
										<?php echo htmlspecialchars($periodLabels[(int) $definition->period_type] ?? '', ENT_QUOTES, 'UTF-8'); ?>
									</span>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if ($isLoggedIn && !$profile) : ?>
			<div class="alert alert-warning mb-0">
				<?php echo Text::_('COM_WENDFOLD_AWARDS_NO_PROFILE'); ?>
			</div>
		<?php elseif (!$isLoggedIn) : ?>
			<div class="alert alert-light border mb-0">
				<?php echo Text::_('COM_WENDFOLD_AWARDS_LOGIN_NOTE'); ?>
			</div>
		<?php endif; ?>

		<?php if ($profile) : ?>
			<section class="hc-panel">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_AWARDS_EARNED_TITLE'); ?></h2>
					</div>
				</div>
				<div class="hc-panel__body">
					<?php if (empty($earned)) : ?>
						<div class="alert alert-light border mb-0">
							<?php echo Text::_('COM_WENDFOLD_AWARDS_EARNED_EMPTY'); ?>
						</div>
					<?php else : ?>
						<div class="hc-earned-list">
							<?php foreach ($earned as $award) : ?>
								<article class="hc-earned-item">
									<div class="hc-earned-item__main">
										<div class="hc-earned-item__title">
											<?php echo htmlspecialchars(LookupTitle::display((string) $award->title), ENT_QUOTES, 'UTF-8'); ?>
										</div>
										<div class="hc-earned-item__meta">
											<span><?php echo htmlspecialchars($periodLabels[(int) $award->period_type] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
											<span><?php echo Text::sprintf('COM_WENDFOLD_AWARDS_EARNED_ON', HTMLHelper::_('date', (string) $award->earned_at, 'M j, Y')); ?></span>
											<?php if ((int) $award->tier > 1) : ?>
												<span><?php echo Text::sprintf('COM_WENDFOLD_AWARDS_EARNED_TIER', (int) $award->tier); ?></span>
											<?php endif; ?>
											<?php if ($award->period_key !== '') : ?>
												<span><?php echo Text::sprintf('COM_WENDFOLD_AWARDS_EARNED_PERIOD', $this->escape((string) $award->period_key)); ?></span>
											<?php endif; ?>
										</div>
										<?php if (!empty($award->description)) : ?>
											<div class="hc-muted mt-2"><?php echo htmlspecialchars((string) $award->description, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php endif; ?>
									</div>
									<span class="hc-badge hc-badge-success"><?php echo Text::_('COM_WENDFOLD_AWARDS_STATUS_EARNED'); ?></span>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

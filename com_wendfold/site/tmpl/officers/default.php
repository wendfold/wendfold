<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\ChapterSubtitle;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$wlParams = ComponentHelper::getParams('com_wendfold');
$chapterSubtitle = ChapterSubtitle::format(
	(string) $wlParams->get('chapter_name', ''),
	(string) $wlParams->get('chapter_number', '')
);

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_OFFICERS_MEET_YOUR_OFFICERS'),
	'subtitle' => $chapterSubtitle !== ''
		? $chapterSubtitle
		: Text::_('COM_WENDFOLD_OFFICERS_CURRENT_ROSTER'),
];
?>

<div class="hc-page hc-page--officers hc-officers-page">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_OFFICERS_CURRENT_ROSTER'); ?></h2>
				</div>
			</div>

			<div class="hc-panel__body">
				<?php if (empty($this->items)) : ?>
					<div class="hc-empty hc-officers-empty">
						<p class="mb-0"><?php echo Text::_('COM_WENDFOLD_OFFICERS_NO_OFFICERS'); ?></p>
					</div>
				<?php else : ?>
					<div class="hc-officers-grid">
						<?php foreach ($this->items as $item) : ?>
							<?php
							$initial = strtoupper(substr((string) ($item->member_name ?? 'O'), 0, 1));
							$termLabel = '';

							if (!empty($item->term_start)) {
								if (!empty($item->term_end)) {
									$termLabel = Text::sprintf(
										'COM_WENDFOLD_OFFICERS_TERM_DATES',
										DateOnly::format($item->term_start, 'M Y'),
										DateOnly::format($item->term_end, 'M Y')
									);
								} else {
									$termLabel = Text::sprintf(
										'COM_WENDFOLD_OFFICERS_TERM_ONGOING',
										DateOnly::format($item->term_start, 'M Y')
									);
								}
							}
							?>
							<article class="hc-officer-card">
								<div class="hc-officer-photo-wrap">
									<?php if (!empty($item->photo)) : ?>
										<img src="<?php echo Uri::root() . htmlspecialchars((string) $item->photo, ENT_QUOTES, 'UTF-8'); ?>"
											alt="<?php echo htmlspecialchars((string) $item->member_name, ENT_QUOTES, 'UTF-8'); ?>"
											class="hc-officer-photo">
									<?php else : ?>
										<div class="hc-officer-photo hc-officer-photo-placeholder">
											<?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?>
										</div>
									<?php endif; ?>
								</div>

								<div class="hc-officer-info">
									<div class="hc-officer-role"><?php echo htmlspecialchars(\Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) ($item->role_title ?? '')), ENT_QUOTES, 'UTF-8'); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps ?></div>
									<div class="hc-officer-name"><?php echo htmlspecialchars((string) $item->member_name, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php if ($termLabel !== '') : ?>
										<div class="hc-officer-term"><?php echo htmlspecialchars($termLabel, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if (!empty($this->historical)) : ?>
			<section class="hc-panel">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_OFFICERS_HISTORICAL_ROSTER'); ?></h2>
					</div>
				</div>

				<div class="hc-panel__body">
					<div class="table-responsive">
						<table class="hc-historical-table">
							<thead>
								<tr>
									<th><?php echo Text::_('COM_WENDFOLD_OFFICERS_HEADING_MEMBER'); ?></th>
									<th><?php echo Text::_('COM_WENDFOLD_OFFICERS_HEADING_ROLE'); ?></th>
									<th><?php echo Text::_('COM_WENDFOLD_OFFICERS_HEADING_TERM'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($this->historical as $item) : ?>
									<?php
									$termDisplay = '';
									if (!empty($item->term_start)) {
										$termDisplay = DateOnly::format($item->term_start, 'M Y');
										if (!empty($item->term_end)) {
											$termDisplay .= ' - ' . DateOnly::format($item->term_end, 'M Y');
										}
									}
									?>
									<tr>
										<td><?php echo htmlspecialchars((string) $item->member_name, ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars(\Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) ($item->role_title ?? '')), ENT_QUOTES, 'UTF-8'); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps ?></td>
										<td class="hc-historical-term"><?php echo htmlspecialchars($termDisplay !== '' ? $termDisplay : ' - ', ENT_QUOTES, 'UTF-8'); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

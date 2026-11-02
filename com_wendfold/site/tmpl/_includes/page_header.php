<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Locals use the wfHeader* prefix so this include cannot overwrite the
// calling template's $item, $title, $key, or other common names.
$wfHeaderConfig = $hcPageHeader ?? [];
$wfHeaderTitle = trim((string) ($wfHeaderConfig['title'] ?? ''));
$wfHeaderTitleTag = (string) ($wfHeaderConfig['titleTag'] ?? 'h1');
$wfHeaderSubtitle = trim((string) ($wfHeaderConfig['subtitle'] ?? ''));
$wfHeaderActionsHtml = trim((string) ($wfHeaderConfig['actionsHtml'] ?? ''));
$wfHeaderBadgesHtml = trim((string) ($wfHeaderConfig['badgesHtml'] ?? ''));
$wfHeaderMetaSource = $wfHeaderConfig['metaItems'] ?? [];

if (!in_array($wfHeaderTitleTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
	$wfHeaderTitleTag = 'h1';
}

if (!is_array($wfHeaderMetaSource)) {
	$wfHeaderMetaSource = [];
}

$wfHeaderMetaItems = [];

foreach ($wfHeaderMetaSource as $wfHeaderMetaEntry) {
	if (is_array($wfHeaderMetaEntry) && array_key_exists('html', $wfHeaderMetaEntry)) {
		$wfHeaderMetaHtml = trim((string) $wfHeaderMetaEntry['html']);

		if ($wfHeaderMetaHtml !== '') {
			$wfHeaderMetaItems[] = ['html' => $wfHeaderMetaHtml];
		}

		continue;
	}

	$wfHeaderMetaText = trim((string) $wfHeaderMetaEntry);

	if ($wfHeaderMetaText !== '') {
		$wfHeaderMetaItems[] = $wfHeaderMetaText;
	}
}

unset($wfHeaderMetaSource, $wfHeaderMetaEntry, $wfHeaderMetaHtml, $wfHeaderMetaText);
?>
<header class="hc-page__head">
	<div class="hc-page__head-main">
		<<?php echo $wfHeaderTitleTag; ?> class="hc-page__title"><?php echo htmlspecialchars($wfHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></<?php echo $wfHeaderTitleTag; ?>>

		<?php if ($wfHeaderSubtitle !== '') : ?>
			<div class="hc-page__subtitle"><?php echo htmlspecialchars($wfHeaderSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<?php if (!empty($wfHeaderMetaItems)) : ?>
			<div class="hc-page__meta">
				<?php foreach ($wfHeaderMetaItems as $wfHeaderMetaEntry) : ?>
					<span><?php echo is_array($wfHeaderMetaEntry)
						? $wfHeaderMetaEntry['html']
						: htmlspecialchars($wfHeaderMetaEntry, ENT_QUOTES, 'UTF-8'); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ($wfHeaderBadgesHtml !== '') : ?>
			<div class="hc-page__badges"><?php echo $wfHeaderBadgesHtml; ?></div>
		<?php endif; ?>
	</div>

	<?php if ($wfHeaderActionsHtml !== '') : ?>
		<div class="hc-page__actions"><?php echo $wfHeaderActionsHtml; ?></div>
	<?php endif; ?>
</header>
<?php
unset(
	$wfHeaderConfig,
	$wfHeaderTitle,
	$wfHeaderTitleTag,
	$wfHeaderSubtitle,
	$wfHeaderActionsHtml,
	$wfHeaderBadgesHtml,
	$wfHeaderMetaItems,
	$wfHeaderMetaEntry
);

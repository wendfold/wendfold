<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$item = $this->item;
$volunteerRoles = $this->volunteerRoles ?? [];
$volunteerBoard = $this->volunteerBoard ?? [];
$eventOpen = !empty($this->eventOpen);
$volunteerManageAction = RouteHelper::task('event.volunteermanage');
$returnUrl = RouteHelper::encodeReturn(RouteHelper::volunteerBoardInternal((int) $item->id));
$detailUrl = RouteHelper::detail($item);
$eventDateLabel = DateOnly::format($item->event_date, 'l, F j, Y');
$destinationLabel = trim((string) ($item->evt_display ?? $item->event_location_custom ?? $item->ksu_display ?? $item->ksu_location_custom ?? ''));
$locationBits = [];
if (!empty($item->evt_city)) {
	$locationBits[] = (string) $item->evt_city;
}
if (!empty($item->evt_state)) {
	$locationBits[] = (string) $item->evt_state;
}
$metaItems = [$eventDateLabel];
if ($destinationLabel !== '') {
	$metaItems[] = $destinationLabel;
}
if (!empty($locationBits)) {
	$metaItems[] = implode(', ', $locationBits);
}

$statusInfo = [
	1 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'class' => 'hc-badge-outline'],
	2 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'), 'class' => 'hc-badge-neutral'],
	3 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'), 'class' => 'hc-badge-danger'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'class' => 'hc-badge-success'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'class' => 'hc-badge-warning'],
];
$type = [
	'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($item->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
	'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($item->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
];
$status = $statusInfo[(int) ($item->status ?? 1)] ?? $statusInfo[1];

$rolesConfigured = count($volunteerBoard['roles'] ?? []);
$waitlistReady = 0;
foreach (($volunteerBoard['roles'] ?? []) as $boardRole) {
	$filled = (int) ($boardRole->filled_slots ?? 0);
	$slotCount = max(1, (int) ($boardRole->slot_count ?? 1));
	if ($filled >= $slotCount) {
		$waitlistReady++;
	}
}

ob_start();
?>
<a class="<?php echo hcButtonClasses('back'); ?>" href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_BACK'); ?>
</a>
<?php
$headerActions = trim((string) ob_get_clean());

ob_start();
?>
<span class="hc-badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<span class="hc-badge <?php echo htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php if (!$eventOpen) : ?>
	<span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ROSTER_VIEW_ONLY'); ?></span>
<?php endif; ?>
<?php
$headerBadges = trim((string) ob_get_clean());

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_PAGE_HEADING'),
	'subtitle' => (string) $item->title,
	'metaItems' => $metaItems,
	'badgesHtml' => $headerBadges,
	'actionsHtml' => $headerActions,
];
?>

<div class="hc-page hc-page--volunteerboard hc-volunteer-page">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<section class="hc-panel">
			<div class="hc-panel__body hc-stack">
				<?php if (!empty($item->volunteer_notes)) : ?>
					<div>
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SECTION'); ?></div>
						<div class="hc-muted"><?php echo nl2br(htmlspecialchars((string) $item->volunteer_notes, ENT_QUOTES, 'UTF-8')); ?></div>
					</div>
				<?php endif; ?>

				<div class="hc-trip-summary-grid hc-volunteer-summary-grid">
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) ($volunteerBoard['active_assignment_count'] ?? 0); ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_ACTIVE'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) ($volunteerBoard['waitlist_count'] ?? 0); ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_WAITLIST'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $rolesConfigured; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_ROLE_COUNT'); ?></div>
					</article>
					<article class="hc-kpi-card">
						<div class="hc-stat-value"><?php echo (int) $waitlistReady; ?></div>
						<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_WAITLIST_READY'); ?></div>
					</article>
				</div>
			</div>
		</section>

		<?php
		$volunteerBoardItem = $item;
		$volunteerBoardData = $volunteerBoard;
		$volunteerBoardManageAction = $volunteerManageAction;
		$volunteerBoardReturnUrl = $returnUrl;
		$volunteerBoardEditable = $eventOpen;
		require dirname(__DIR__) . '/_includes/volunteer_board.php';
		?>
	</div>
</div>

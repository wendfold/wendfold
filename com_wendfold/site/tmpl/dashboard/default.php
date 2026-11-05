<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Database column names are snake_case on dashboard rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricDisplayLabel;
use Wendfold\Component\Wendfold\Site\Helper\ChapterSubtitle;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\EventReturnDisplay;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$profile = $this->profile;
$stats = $this->stats;
$upcomingRsvps = $this->upcomingRsvps;
$awardSnapshot = $this->awardSnapshot;
$year = (int) ($stats->year ?? 0);

$myRsvpsUrl = RouteHelper::myRsvps();
$routeToEventDetail = static function ($eventLike): string {
	return RouteHelper::detail($eventLike);
};
$editProfileUrl = RouteHelper::profileEdit();
$awardsUrl = RouteHelper::awards();

$avatarUrl = '';
if ($profile && !empty($profile->photo)) {
	$avatarUrl = Uri::root() . ltrim((string) $profile->photo, '/');
}

$name = $profile ? (string) ($profile->name ?? '') : '';
$initial = '';
if (trim($name) !== '') {
	$firstChar = function_exists('mb_substr') ? mb_substr(trim($name), 0, 1) : substr(trim($name), 0, 1);
	$initial = strtoupper((string) $firstChar);
}

$duesBadge = 'bg-secondary';
$duesLabel = Text::_('COM_WENDFOLD_DASHBOARD_DUES_NO_RECORD');
switch ($stats->dues_status ?? 'none') {
	case 'current':
		$duesBadge = 'bg-success';
		$duesLabel = Text::_('COM_WENDFOLD_DASHBOARD_DUES_CURRENT');
		break;

	case 'expiring':
		$duesBadge = 'bg-warning text-dark';
		$duesLabel = Text::_('COM_WENDFOLD_DASHBOARD_DUES_EXPIRING');
		break;

	case 'expired':
		$duesBadge = 'bg-danger';
		$duesLabel = Text::_('COM_WENDFOLD_DASHBOARD_DUES_EXPIRED');
		break;
}

$renewalYear = !empty($stats->renewal_date) ? substr((string) $stats->renewal_date, 0, 4) : '--';

$formatMetricValue = static function (float $value, string $metric): string {
	if ($metric === 'miles') {
		$rounded = round($value, 1);
		$decimals = abs($rounded - round($rounded)) < 0.001 ? 0 : 1;

		return number_format($rounded, $decimals);
	}

	return number_format((int) round($value));
};

$metricCards = $stats->metrics ?? [];

ob_start();
?>
<a class="<?php echo hcButtonClasses('secondary'); ?>" href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=activitylog'), ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_ACTIVITY_LOG_LINK'); ?>
</a>
<a class="<?php echo hcButtonClasses('secondary'); ?>" href="<?php echo htmlspecialchars(RouteHelper::myActivities(), ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_DASHBOARD_LINK_MYACTIVITIES'); ?>
</a>
<a class="<?php echo hcButtonClasses('secondary'); ?>" href="<?php echo htmlspecialchars($editProfileUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo Text::_('COM_WENDFOLD_DASHBOARD_EDIT_PROFILE'); ?>
</a>
<?php
$headerActions = trim((string) ob_get_clean());

$wlParams = ComponentHelper::getParams('com_wendfold');
$chapterSubtitle = ChapterSubtitle::format(
	(string) $wlParams->get('chapter_name', ''),
	(string) $wlParams->get('chapter_number', '')
);

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_DASHBOARD_TITLE'),
	'subtitle' => $chapterSubtitle,
	'actionsHtml' => $headerActions,
];
?>

<div class="hc-page hc-page--dashboard hc-dashboard">

<?php if (!$profile) : ?>
	<div class="alert alert-warning mb-0">
		<?php echo Text::_('COM_WENDFOLD_DASHBOARD_NO_PROFILE'); ?>
	</div>
<?php else : ?>

	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__layout hc-page__layout--sidebar hc-dashboard-layout">
		<section class="hc-panel hc-dashboard-identity-card hc-dashboard-layout__profile">
			<div class="hc-panel__body">
				<div class="hc-profile-summary">
					<div class="hc-profile-summary__identity">
						<?php if ($avatarUrl) : ?>
							<img
								class="hc-avatar hc-avatar--dashboard"
								src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
								alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
								width="112"
								height="112"
								loading="lazy"
								decoding="async"
							>
						<?php else : ?>
							<div class="hc-avatar-ph hc-avatar-ph--dashboard" aria-hidden="true"><?php echo htmlspecialchars($initial ?: '?', ENT_QUOTES, 'UTF-8'); ?></div>
						<?php endif; ?>

						<div class="hc-profile-summary__text">
							<div class="hc-profile-summary__name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php if (!empty($profile->member_number)) : ?>
								<div class="hc-muted">Member #<?php echo htmlspecialchars((string) $profile->member_number, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</section>

		<aside class="hc-page__aside hc-dashboard-layout__stats">
			<section class="hc-panel hc-dashboard-stats-panel">
				<div class="hc-panel__body">
					<div class="hc-dashboard-stats-grid">
						<?php foreach ($metricCards as $metricCard) : ?>
							<?php
							$metricTotal = number_format((float) $metricCard->total, (int) $metricCard->decimals, '.', '');
							$metricLabel = trim(
								$year . ' ' . MetricDisplayLabel::withOptionalUnit(
									(string) $metricCard->label,
									(string) $metricCard->unit
								)
							);
							?>
							<div class="hc-kpi-card">
								<div class="hc-stat-value"><?php echo htmlspecialchars($metricTotal, ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="hc-kpi-label"><?php echo htmlspecialchars($metricLabel, ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						<?php endforeach; ?>

						<div class="hc-kpi-card">
							<div class="mb-2">
								<span class="badge rounded-pill <?php echo htmlspecialchars($duesBadge, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($duesLabel, ENT_QUOTES, 'UTF-8'); ?></span>
							</div>
							<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_DUES_STATUS'); ?></div>
						</div>

						<div class="hc-kpi-card">
							<div class="hc-stat-value"><?php echo htmlspecialchars($renewalYear, ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="hc-kpi-label"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_RENEWAL_DATE'); ?></div>
						</div>
					</div>
				</div>
			</section>
		</aside>

		<section class="hc-panel hc-dashboard-layout__upcoming">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_UPCOMING_RSVPS'); ?></h2>
					<div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_LINK_MYRSVPS'); ?></div>
				</div>

				<div class="hc-panel__actions">
					<a class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>" href="<?php echo htmlspecialchars($myRsvpsUrl, ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo Text::_('COM_WENDFOLD_DASHBOARD_LINK_MYRSVPS'); ?>
					</a>
				</div>
			</div>

			<div class="hc-panel__body">
				<?php if (empty($upcomingRsvps)) : ?>
					<div class="alert alert-light border mb-0">
						<?php echo Text::_('COM_WENDFOLD_DASHBOARD_NO_UPCOMING_RSVPS'); ?>
					</div>
				<?php else : ?>
					<div class="list-group list-group-flush hc-list-group">
						<?php foreach ($upcomingRsvps as $rsvp) : ?>
							<?php
							$url = $routeToEventDetail($rsvp);
							$type = [
								'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($rsvp->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
								'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($rsvp->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
							];
							$date = new DateTime((string) $rsvp->event_date);
							$loc = trim((string) (($rsvp->event_city ?? '') . ($rsvp->event_state ? ', ' . $rsvp->event_state : '')));
							$meta = DateOnly::format((string) $rsvp->event_date, 'D, M j, Y');
							$showReturn = EventReturnDisplay::shouldShow(
								!empty($rsvp->is_riding_event),
								(string) ($rsvp->start_date ?? ''),
								(string) ($rsvp->end_date ?? ''),
								(string) ($rsvp->event_date ?? '')
							);
							$departureLine = '';
							$returnLine = '';

							if (!empty($rsvp->ksu_time)) {
								$departureLine = Text::_('COM_WENDFOLD_EVENT_KSU') . ' '
									. date('g:i A', strtotime((string) $rsvp->ksu_time));
							}

							if ($showReturn && !empty($rsvp->ksd_time)) {
								$returnLine = Text::_('COM_WENDFOLD_EVENT_KSD') . ' '
									. date('g:i A', strtotime((string) $rsvp->ksd_time));
							}
							?>
							<a class="list-group-item list-group-item-action hc-dashboard-event-row" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
								<div class="hc-date-badge" aria-hidden="true">
									<div class="hc-date-mon"><?php echo $date->format('M'); ?></div>
									<div class="hc-date-day"><?php echo $date->format('j'); ?></div>
								</div>

								<div class="hc-dashboard-event-info">
									<div class="fw-semibold text-truncate"><?php echo htmlspecialchars((string) $rsvp->title, ENT_QUOTES, 'UTF-8'); ?></div>
									<div class="small text-muted text-truncate"><?php echo htmlspecialchars($meta . ($loc ? ' - ' . $loc : ''), ENT_QUOTES, 'UTF-8'); ?></div>
									<?php if ($departureLine !== '') : ?>
										<div class="small text-muted text-truncate"><?php echo htmlspecialchars($departureLine, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
									<?php if ($returnLine !== '') : ?>
										<div class="small text-muted text-truncate"><?php echo htmlspecialchars($returnLine, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>
								</div>

								<span class="hc-badge hc-dashboard-event-badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8'); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="hc-panel hc-dashboard-layout__awards">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_AWARDS'); ?></h2>
				</div>

				<div class="hc-panel__actions">
					<a class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>" href="<?php echo htmlspecialchars($awardsUrl, ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo Text::_('COM_WENDFOLD_DASHBOARD_LINK_AWARDS'); ?>
					</a>
				</div>
			</div>

			<div class="hc-panel__body">
				<div class="wendfold-awards-dashboard">
					<div class="wendfold-awards-dashboard__latest">
						<div class="hc-meta-label"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_AWARDS_LATEST'); ?></div>
						<?php if ($awardSnapshot && !empty($awardSnapshot->latest)) : ?>
							<div class="wendfold-awards-dashboard__latest-name"><?php echo htmlspecialchars(LookupTitle::display((string) $awardSnapshot->latest->title), ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="hc-muted">
								<?php echo Text::sprintf('COM_WENDFOLD_AWARDS_EARNED_ON', HTMLHelper::_('date', (string) $awardSnapshot->latest->earned_at, Text::_('DATE_FORMAT_LC4'))); ?>
								<?php if ((int) ($awardSnapshot->latest->tier ?? 1) > 1) : ?>
									- <?php echo Text::sprintf('COM_WENDFOLD_AWARDS_EARNED_TIER', (int) $awardSnapshot->latest->tier); ?>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<div class="hc-muted"><?php echo Text::_('COM_WENDFOLD_DASHBOARD_AWARDS_NONE_EARNED'); ?></div>
						<?php endif; ?>
					</div>
					<?php if ($awardSnapshot && !empty($awardSnapshot->earned)) : ?>
						<ul class="list-unstyled mb-0 mt-3">
							<?php foreach ($awardSnapshot->earned as $grant) : ?>
								<li class="mb-1">
									<span class="hc-strong"><?php echo htmlspecialchars(LookupTitle::display((string) $grant->title), ENT_QUOTES, 'UTF-8'); ?></span>
									<span class="hc-muted"> · <?php echo HTMLHelper::_('date', (string) $grant->earned_at, Text::_('DATE_FORMAT_LC4')); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</section>
	</div>

<?php endif; ?>

</div>

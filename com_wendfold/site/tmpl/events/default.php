<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Site\Helper\ChapterSubtitle;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
require_once dirname(__DIR__) . '/_includes/button_helper.php';

$items = $this->items;
$state = $this->state;
$app = Factory::getApplication();
$input = $app->getInput();
$itemId = $input->getInt('Itemid');

$filterSearch = trim((string) StateValue::get($state, 'filter.search', ''));
$filterTimeframe = (string) StateValue::get($state, 'filter.timeframe', 'upcoming');
$filterEventType = (int) StateValue::get($state, 'filter.event_type', 0);
$filterStatus = (int) StateValue::get($state, 'filter.status', 0);
$filterView = strtolower(trim((string) StateValue::get($state, 'filter.view', '')));

if (!in_array($filterTimeframe, ['upcoming', 'past'], true)) {
	$filterTimeframe = 'upcoming';
}

if (!in_array($filterView, ['cards', 'list'], true)) {
	$filterView = '';
}

$viewMode = $filterView !== '' ? $filterView : 'auto';
$hasFilters = $filterSearch !== ''
	|| $filterTimeframe !== 'upcoming'
	|| $filterEventType > 0
	|| $filterStatus > 0;

$firstName = static function ($full): string {
	$full = trim((string) $full);

	if ($full === '') {
		return '';
	}

	$parts = preg_split('/\s+/', $full);

	return $parts[0] ?? $full;
};

$statusInfo = [
	1 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'class' => 'hc-badge-outline'],
	2 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'), 'class' => 'hc-badge-neutral'],
	3 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'), 'class' => 'hc-badge-danger'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'class' => 'hc-badge-success'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'class' => 'hc-badge-warning'],
];

$buildEventsRoute = static function (array $overrides = []) use ($itemId): string {
	$query = [
		'option' => 'com_wendfold',
		'view' => 'events',
	];

	if ($itemId > 0) {
		$query['Itemid'] = $itemId;
	}

	foreach ($overrides as $key => $value) {
		if ($value === null || $value === '' || $value === 0 || $value === '0') {
			unset($query[$key]);
			continue;
		}

		$query[$key] = $value;
	}

	return RouteHelper::route($query);
};

$currentRouteState = [
	'filter_search' => $filterSearch,
	'filter_timeframe' => $filterTimeframe,
	'filter_event_type' => $filterEventType,
	'filter_status' => $filterStatus,
];

$cardsViewUrl = $buildEventsRoute($currentRouteState + ['filter_view' => 'cards']);
$listViewUrl = $buildEventsRoute($currentRouteState + ['filter_view' => 'list']);
$clearFiltersUrl = $buildEventsRoute($filterView !== '' ? ['filter_view' => $filterView] : []);
$filterAction = $buildEventsRoute();
$filtersPanelId = 'hc-events-filters-panel';
$filterToggleId = 'hc-events-filter-toggle';

$formatEvent = static function ($item) use ($statusInfo, $firstName): array {
	$url = RouteHelper::detail($item);
	$type = [
		'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($item->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::siteBadgeClass((int) ($item->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
	];
	$status = (int) ($item->status ?? 1);
	$statusMeta = $statusInfo[$status] ?? $statusInfo[1];
	$showStatus = $status !== 1;
	$cancelled = $status === 3;
	$isRide = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isRide((int) ($item->event_behavior ?? 0)); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	if ($isRide) {
		$location = $item->ksu_location_name ?: $item->ksu_location_custom ?: null;
		$city = $item->ksu_location_city ?: ($item->ksu_city ?? '');
		$state = $item->ksu_location_state ?: ($item->ksu_state ?? '');
		$cityState = trim($city . ($state ? ', ' . $state : ''));
	} else {
		$location = $item->location_name ?: $item->event_location_custom ?: null;
		$city = (string) ($item->event_city ?? '');
		$state = (string) ($item->event_state ?? '');
		$cityState = trim($city . ($state ? ', ' . $state : ''));
	}

	$displayLoc = $location ?: $cityState ?: '';
	$excerpt = OutputEscape::hasVisibleContent((string) ($item->description ?? ''))
		? OutputEscape::htmlExcerpt((string) $item->description, 100)
		: '';

	$rcName = trim((string) ($item->road_captain_preferred ?? ''));

	if ($rcName === '') {
		$rcName = $firstName($item->road_captain_name ?? '');
	}

	$swName = trim((string) ($item->sweep_preferred ?? ''));

	if ($swName === '') {
		$swName = $firstName($item->sweep_name ?? '');
	}

	$crew = '';

	if ($rcName !== '' && $swName !== '') {
		$crew = $rcName . ' & ' . $swName;
	} elseif ($rcName !== '') {
		$crew = $rcName;
	} elseif ($swName !== '') {
		$crew = $swName;
	}

	$ksuTimeLabel = '';

	if (!empty($item->ksu_time)) {
		$ksuTimeLabel = date('g:i A', strtotime((string) $item->ksu_time));
	}

	$mileageLabel = '';

	if (isset($item->distance_credit) && (float) $item->distance_credit > 0) {
		$mileageLabel = '~' . number_format((float) $item->distance_credit, 0) . ' mi';
	}

	return [
		'url' => $url,
		'type' => $type,
		'status' => $statusMeta,
		'showStatus' => $showStatus,
		'cancelled' => $cancelled,
		'title' => (string) $item->title,
		'dateLabel' => DateOnly::format($item->event_date, 'l, F j, Y'),
		'monthLabel' => DateOnly::format($item->event_date, 'M'),
		'dayLabel' => DateOnly::format($item->event_date, 'j'),
		'weekdayLabel' => DateOnly::format($item->event_date, 'D'),
		'ksuTimeLabel' => $ksuTimeLabel,
		'locationLabel' => $displayLoc,
		'excerpt' => $excerpt,
		'crew' => $crew,
		'mileageLabel' => $mileageLabel,
	];
};

ob_start();
?>
<div class="hc-events-headtools" role="group" aria-label="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_FILTER_VIEW'), ENT_QUOTES, 'UTF-8'); ?>">
	<div class="hc-events-viewcontrols" role="group" aria-label="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_FILTER_VIEW'), ENT_QUOTES, 'UTF-8'); ?>">
		<a href="<?php echo htmlspecialchars($cardsViewUrl, ENT_QUOTES, 'UTF-8'); ?>"
		   class="hc-events-toolbtn hc-events-viewcontrol hc-events-viewcontrol--cards<?php echo $filterView === 'cards' ? ' is-active' : ''; ?>"
		   aria-label="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_VIEW_CARDS_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"
		   title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_VIEW_CARDS_LABEL'), ENT_QUOTES, 'UTF-8'); ?>">
			<span class="hc-events-icon hc-events-icon--cards" aria-hidden="true">
				<span></span>
				<span></span>
				<span></span>
				<span></span>
			</span>
			<span class="visually-hidden"><?php echo Text::_('COM_WENDFOLD_EVENTS_VIEW_CARDS_LABEL'); ?></span>
		</a>
		<a href="<?php echo htmlspecialchars($listViewUrl, ENT_QUOTES, 'UTF-8'); ?>"
		   class="hc-events-toolbtn hc-events-viewcontrol hc-events-viewcontrol--list<?php echo $filterView === 'list' ? ' is-active' : ''; ?>"
		   aria-label="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_VIEW_LIST_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"
		   title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_VIEW_LIST_LABEL'), ENT_QUOTES, 'UTF-8'); ?>">
			<span class="hc-events-icon hc-events-icon--list" aria-hidden="true">
				<span></span>
				<span></span>
				<span></span>
			</span>
			<span class="visually-hidden"><?php echo Text::_('COM_WENDFOLD_EVENTS_VIEW_LIST_LABEL'); ?></span>
		</a>
	</div>

	<button type="button"
			id="<?php echo htmlspecialchars($filterToggleId, ENT_QUOTES, 'UTF-8'); ?>"
			class="hc-events-toolbtn hc-events-filtertoggle<?php echo $hasFilters ? ' is-active' : ''; ?>"
			aria-controls="<?php echo htmlspecialchars($filtersPanelId, ENT_QUOTES, 'UTF-8'); ?>"
			aria-expanded="<?php echo $hasFilters ? 'true' : 'false'; ?>"
			aria-label="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_FILTERS'), ENT_QUOTES, 'UTF-8'); ?>"
			title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_FILTERS'), ENT_QUOTES, 'UTF-8'); ?>">
		<span class="hc-events-icon hc-events-icon--filter" aria-hidden="true"></span>
		<span class="visually-hidden"><?php echo Text::_('COM_WENDFOLD_EVENTS_FILTERS'); ?></span>
	</button>
</div>
<?php
$headerActions = (string) ob_get_clean();

$wlParams = ComponentHelper::getParams('com_wendfold');
$chapterSubtitle = ChapterSubtitle::format(
	(string) $wlParams->get('chapter_name', ''),
	(string) $wlParams->get('chapter_number', '')
);

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_EVENTS_TITLE'),
	'subtitle' => $chapterSubtitle,
	'actionsHtml' => $headerActions,
];
?>
<div class="hc-page hc-page--events hc-events" data-view-mode="<?php echo htmlspecialchars($viewMode, ENT_QUOTES, 'UTF-8'); ?>">
	<noscript><style>#<?php echo $filtersPanelId; ?>{display:block !important;} .hc-events-filtertoggle{display:none !important;}</style></noscript>
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main">
		<section id="<?php echo htmlspecialchars($filtersPanelId, ENT_QUOTES, 'UTF-8'); ?>"
				 class="hc-panel hc-events-toolbar<?php echo $hasFilters ? ' is-open' : ''; ?>"
				 <?php echo $hasFilters ? '' : ' hidden'; ?>>
			<div class="hc-panel__body hc-events-toolbar__body">
				<form action="<?php echo htmlspecialchars($filterAction, ENT_QUOTES, 'UTF-8'); ?>" method="get" class="hc-events-filters">
					<input type="hidden" name="option" value="com_wendfold">
					<input type="hidden" name="view" value="events">
					<?php if ($itemId > 0) : ?>
						<input type="hidden" name="Itemid" value="<?php echo (int) $itemId; ?>">
					<?php endif; ?>
					<input type="hidden" name="filter_view" value="<?php echo htmlspecialchars($filterView, ENT_QUOTES, 'UTF-8'); ?>">

					<div class="hc-events-filters__compact">
						<div class="hc-events-field hc-events-field--search">
							<label class="hc-meta-label" for="hc-filter-search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
							<input
								type="search"
								name="filter_search"
								id="hc-filter-search"
								class="form-control"
								value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>"
								placeholder="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_EVENTS_SEARCH_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>">
						</div>

						<div class="hc-events-field">
							<label class="hc-meta-label" for="hc-filter-timeframe"><?php echo Text::_('COM_WENDFOLD_EVENTS_FILTER_SHOW'); ?></label>
							<select name="filter_timeframe" id="hc-filter-timeframe" class="form-select">
								<option value="upcoming" <?php echo $filterTimeframe === 'upcoming' ? 'selected' : ''; ?>>
									<?php echo Text::_('COM_WENDFOLD_EVENTS_FILTER_UPCOMING'); ?>
								</option>
								<option value="past" <?php echo $filterTimeframe === 'past' ? 'selected' : ''; ?>>
									<?php echo Text::_('COM_WENDFOLD_EVENTS_FILTER_PAST'); ?>
								</option>
							</select>
						</div>

						<div class="hc-events-field">
							<label class="hc-meta-label" for="hc-filter-type"><?php echo Text::_('COM_WENDFOLD_COL_TYPE'); ?></label>
							<select name="filter_event_type" id="hc-filter-type" class="form-select">
								<option value="0"><?php echo Text::_('COM_WENDFOLD_FILTER_ALL_TYPES'); ?></option>
								<?php foreach ($this->eventTypes as $eventTypeOption) : ?>
									<option value="<?php echo (int) $eventTypeOption->id; ?>"<?php echo $filterEventType === (int) $eventTypeOption->id ? ' selected' : ''; ?>>
										<?php echo htmlspecialchars(\Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) $eventTypeOption->title), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="hc-events-field">
							<label class="hc-meta-label" for="hc-filter-status"><?php echo Text::_('COM_WENDFOLD_COL_STATUS'); ?></label>
							<select name="filter_status" id="hc-filter-status" class="form-select">
								<option value="0"><?php echo Text::_('COM_WENDFOLD_FILTER_ALL_STATUSES'); ?></option>
								<option value="1" <?php echo $filterStatus === 1 ? 'selected' : ''; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'); ?></option>
								<option value="4" <?php echo $filterStatus === 4 ? 'selected' : ''; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'); ?></option>
								<option value="5" <?php echo $filterStatus === 5 ? 'selected' : ''; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'); ?></option>
								<option value="3" <?php echo $filterStatus === 3 ? 'selected' : ''; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'); ?></option>
								<option value="2" <?php echo $filterStatus === 2 ? 'selected' : ''; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'); ?></option>
							</select>
						</div>

						<div class="hc-events-filter-actions">
							<button type="submit" class="<?php echo hcButtonClasses('action', 'hc-btn-sm'); ?>">
								<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>
							</button>
							<a href="<?php echo htmlspecialchars($clearFiltersUrl, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>">
								<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
							</a>
						</div>
					</div>
				</form>
			</div>
		</section>

		<?php if (empty($items)) : ?>
			<div class="alert alert-light border mb-0">
				<?php echo $hasFilters ? Text::_('JGLOBAL_NO_MATCHING_RESULTS') : Text::_('COM_WENDFOLD_EVENTS_NO_EVENTS'); ?>
			</div>
		<?php else : ?>
			<div class="hc-events-results" data-view="<?php echo htmlspecialchars($viewMode, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="hc-events-list">
					<?php foreach ($items as $item) : ?>
						<?php $event = $formatEvent($item); ?>
						<a href="<?php echo htmlspecialchars($event['url'], ENT_QUOTES, 'UTF-8'); ?>" class="hc-event-row <?php echo $event['cancelled'] ? 'hc-cancelled' : ''; ?>">
							<div class="hc-event-row__date">
								<span class="hc-event-row__month"><?php echo htmlspecialchars($event['monthLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
								<span class="hc-event-row__day"><?php echo htmlspecialchars($event['dayLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
								<span class="hc-event-row__weekday"><?php echo htmlspecialchars($event['weekdayLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
							</div>

							<div class="hc-event-row__main">
								<div class="hc-badge-meta">
									<span class="hc-badge <?php echo htmlspecialchars($event['type']['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($event['type']['label'], ENT_QUOTES, 'UTF-8'); ?></span>
									<?php if ($event['showStatus']) : ?>
										<span class="hc-badge <?php echo htmlspecialchars($event['status']['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($event['status']['label'], ENT_QUOTES, 'UTF-8'); ?></span>
									<?php endif; ?>
								</div>

								<h2 class="hc-event-row__title"><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></h2>

								<?php if ($event['ksuTimeLabel'] !== '' || $event['locationLabel'] !== '') : ?>
									<div class="hc-event-row__meta">
										<?php if ($event['ksuTimeLabel'] !== '') : ?>
											<span><?php echo OutputEscape::termWithTooltipAndValue(Text::_('COM_WENDFOLD_EVENT_KSU'), Text::_('COM_WENDFOLD_EVENT_KSU_DESC'), $event['ksuTimeLabel']); ?></span>
										<?php endif; ?>
										<?php if ($event['locationLabel'] !== '') : ?>
											<span><?php echo htmlspecialchars($event['locationLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<?php if ($event['crew'] !== '' || $event['mileageLabel'] !== '') : ?>
									<div class="hc-event-row__submeta">
										<?php if ($event['crew'] !== '') : ?>
											<span class="hc-event-row__crew"><?php echo htmlspecialchars($event['crew'], ENT_QUOTES, 'UTF-8'); ?></span>
										<?php endif; ?>
										<?php if ($event['mileageLabel'] !== '') : ?>
											<span><?php echo htmlspecialchars($event['mileageLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="hc-events-grid">
					<?php foreach ($items as $item) : ?>
						<?php $event = $formatEvent($item); ?>
						<a href="<?php echo htmlspecialchars($event['url'], ENT_QUOTES, 'UTF-8'); ?>" class="hc-event-card <?php echo $event['cancelled'] ? 'hc-cancelled' : ''; ?>">
							<div class="hc-event-card-body">
								<div class="hc-event-card-head">
									<div class="hc-event-card-rail">
										<div class="hc-event-card-badges">
											<span class="hc-badge <?php echo htmlspecialchars($event['type']['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($event['type']['label'], ENT_QUOTES, 'UTF-8'); ?></span>
											<?php if ($event['showStatus']) : ?>
												<span class="hc-badge <?php echo htmlspecialchars($event['status']['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($event['status']['label'], ENT_QUOTES, 'UTF-8'); ?></span>
											<?php endif; ?>
										</div>

										<div class="hc-event-row__date hc-event-card__date">
											<span class="hc-event-row__month"><?php echo htmlspecialchars($event['monthLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
											<span class="hc-event-row__day"><?php echo htmlspecialchars($event['dayLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
											<span class="hc-event-row__weekday"><?php echo htmlspecialchars($event['weekdayLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
										</div>
									</div>

									<div class="hc-event-card-main">
										<h2 class="hc-event-card-title"><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></h2>

										<?php if ($event['ksuTimeLabel'] !== '' || $event['locationLabel'] !== '') : ?>
											<div class="hc-event-card-loc">
												<?php if ($event['ksuTimeLabel'] !== '') : ?>
													<span class="hc-event-card-loc__line"><?php echo OutputEscape::termWithTooltipAndValue(Text::_('COM_WENDFOLD_EVENT_KSU'), Text::_('COM_WENDFOLD_EVENT_KSU_DESC'), $event['ksuTimeLabel']); ?></span>
												<?php endif; ?>
												<?php if ($event['locationLabel'] !== '') : ?>
													<span class="hc-event-card-loc__line"><?php echo htmlspecialchars($event['locationLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
												<?php endif; ?>
											</div>
										<?php endif; ?>
									</div>
								</div>

								<?php if ($event['excerpt'] !== '') : ?>
									<p><?php echo $event['excerpt']; ?></p>
								<?php endif; ?>
							</div>

							<?php if ($event['crew'] !== '' || $event['mileageLabel'] !== '') : ?>
								<div class="hc-event-card-foot">
									<?php if ($event['crew'] !== '') : ?>
										<span class="hc-event-card-crew"><?php echo htmlspecialchars($event['crew'], ENT_QUOTES, 'UTF-8'); ?></span>
									<?php else : ?>
										<span></span>
									<?php endif; ?>

									<?php if ($event['mileageLabel'] !== '') : ?>
										<span class="hc-event-card-miles"><?php echo htmlspecialchars($event['mileageLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var root = document.querySelector('.hc-events');
	var toggle = document.getElementById('<?php echo $filterToggleId; ?>');
	var panel = document.getElementById('<?php echo $filtersPanelId; ?>');
	var searchInput;

	if (!root || !toggle || !panel) {
		return;
	}

	searchInput = panel.querySelector('#hc-filter-search');

	var setOpen = function (isOpen) {
		panel.hidden = !isOpen;
		panel.classList.toggle('is-open', isOpen);
		toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		toggle.classList.toggle('is-active', isOpen);
	};

	setOpen(toggle.getAttribute('aria-expanded') === 'true');

	toggle.addEventListener('click', function () {
		var nextOpen = panel.hidden;

		setOpen(nextOpen);

		if (nextOpen && searchInput) {
			searchInput.focus();
		}
	});
});
</script>

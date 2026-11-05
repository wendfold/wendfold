<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Events List Layout
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

require dirname(__DIR__) . '/_includes/sortable_list_behavior.php';


$listOrder = $this->escape((string) StateValue::get($this->state, 'list.ordering', 'e.event_date'));
$listDirn  = $this->escape((string) StateValue::get($this->state, 'list.direction', 'DESC'));

$filterSearch    = trim((string) StateValue::get($this->state, 'filter.search', ''));
$filterType      = (string) StateValue::get($this->state, 'filter.event_type', '');
$filterStatus    = (string) StateValue::get($this->state, 'filter.status', '');
$filterPublished = (string) StateValue::get($this->state, 'filter.published', '');

$hasFilters = ($filterSearch !== '')
	|| ($filterType !== '')
	|| ($filterStatus !== '')
	|| ($filterPublished !== '');

$user = Factory::getApplication()->getIdentity();
$canCreate = $user->authorise('core.create', 'com_wendfold');

// Event status labels and badge colors
$statusLabels = [
	1 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'), 'class' => 'bg-info'],
	5 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'), 'class' => 'bg-warning text-dark'],
	4 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'), 'class' => 'bg-success'],
	3 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'), 'class' => 'bg-danger'],
	2 => ['label' => Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'), 'class' => 'bg-secondary'],
];
?>

<form action="<?php echo Route::_('index.php?option=com_wendfold&view=events'); ?>"
	  method="post"
	  name="adminForm"
	  id="adminForm">

	<?php if (!empty($this->items) || $hasFilters) : ?>
	<!-- Search & Filters -->
	<div class="row mb-3">
		<div class="col-md-3 d-flex gap-2">
			<input type="text"
				   name="filter_search"
				   id="filter_search"
				   class="form-control"
				   placeholder="<?php echo Text::_('COM_WENDFOLD_EVENTS_SEARCH_PLACEHOLDER'); ?>"
				   value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES); ?>">
			<button type="submit" class="btn btn-primary">
				<span class="icon-search" aria-hidden="true"></span>
				<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>
			</button>
			<button type="button" class="btn btn-secondary"
					onclick="document.getElementById('filter_search').value='';this.form.submit();">
				<?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
			</button>
		</div>

		<div class="col-md-3">
			<select name="filter_event_type" class="form-select" onchange="this.form.submit()">
				<option value=""><?php echo Text::_('COM_WENDFOLD_FILTER_ALL_TYPES'); ?></option>
				<?php foreach ($this->eventTypes as $eventType) : ?>
					<option value="<?php echo (int) $eventType->id; ?>"<?php echo (string) StateValue::get($this->state, 'filter.event_type') === (string) $eventType->id ? ' selected' : ''; ?>>
						<?php echo htmlspecialchars(\Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) $eventType->title), ENT_QUOTES, 'UTF-8'); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="col-md-3">
			<select name="filter_status" class="form-select" onchange="this.form.submit()">
				<option value=""><?php echo Text::_('COM_WENDFOLD_FILTER_ALL_STATUSES'); ?></option>
				<option value="1" <?php echo StateValue::get($this->state, 'filter.status') === '1' ? 'selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_SCHEDULED'); ?>
				</option>
				<option value="5" <?php echo StateValue::get($this->state, 'filter.status') === '5' ? 'selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_WEATHER_WATCH'); ?>
				</option>
				<option value="4" <?php echo StateValue::get($this->state, 'filter.status') === '4' ? 'selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_CONFIRMED'); ?>
				</option>
				<option value="3" <?php echo StateValue::get($this->state, 'filter.status') === '3' ? 'selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_CANCELLED'); ?>
				</option>
				<option value="2" <?php echo StateValue::get($this->state, 'filter.status') === '2' ? 'selected' : ''; ?>>
					<?php echo Text::_('COM_WENDFOLD_EVENT_STATUS_COMPLETED'); ?>
				</option>
			</select>
		</div>

		<div class="col-md-3">
			<select name="filter_published" class="form-select" onchange="this.form.submit()">
				<option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
				<option value="1"<?php echo $filterPublished === '1' ? ' selected' : ''; ?>>
					<?php echo Text::_('JPUBLISHED'); ?>
				</option>
				<option value="0"<?php echo $filterPublished === '0' ? ' selected' : ''; ?>>
					<?php echo Text::_('JUNPUBLISHED'); ?>
				</option>
				<option value="2"<?php echo $filterPublished === '2' ? ' selected' : ''; ?>>
					<?php echo Text::_('JARCHIVED'); ?>
				</option>
				<option value="-2"<?php echo $filterPublished === '-2' ? ' selected' : ''; ?>>
					<?php echo Text::_('JTRASHED'); ?>
				</option>
			</select>
		</div>
	</div>
	<?php endif; ?>

	<?php if (empty($this->items) && !$hasFilters) : ?>
		<div class="text-center py-5">
			<div class="mb-3">
				<span class="icon-calendar" aria-hidden="true" style="font-size:4rem"></span>
			</div>
			<h2 class="mb-2"><?php echo Text::_('COM_WENDFOLD_EMPTY_EVENTS_TITLE'); ?></h2>
			<p class="text-muted mb-4"><?php echo Text::_('COM_WENDFOLD_EMPTY_EVENTS_DESC'); ?></p>
			<?php if ($canCreate) : ?>
				<a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_wendfold&task=event.add'); ?>">
					<span class="icon-plus" aria-hidden="true"></span>
					<?php echo Text::_('COM_WENDFOLD_EMPTY_EVENTS_BUTTON'); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="text-center" style="width:30px">
					<?php echo HTMLHelper::_('grid.checkall'); ?>
				</th>
				<th>
					<?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_TITLE', 'e.title', $listDirn, $listOrder); ?>
				</th>
				<th class="text-center" style="width:110px">
					<?php echo Text::_('COM_WENDFOLD_COL_TYPE'); ?>
				</th>
				<th class="text-nowrap" style="width:190px">
					<?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_DATE', 'e.event_date', $listDirn, $listOrder); ?>
				</th>
				<th class="text-nowrap" style="width:200px">
					<?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_LOCATION', 'location_sort', $listDirn, $listOrder); ?>
				</th>
				<th class="text-center text-nowrap" style="width:90px">
					<?php echo HTMLHelper::_('grid.sort', 'COM_WENDFOLD_COL_MILES', 'distance_credit', $listDirn, $listOrder); ?>
				</th>
				<th class="text-center" style="width:50px">
					<?php echo Text::_('COM_WENDFOLD_COL_HDI'); ?>
				</th>
				<th class="text-center" style="width:60px">
					<?php echo Text::_('JPUBLISHED'); ?>
				</th>
				<th class="text-center" style="width:60px">
					<?php echo Text::_('COM_WENDFOLD_COL_ATTENDANCE'); ?>
				</th>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($this->items)) : ?>
			<tr>
				<td colspan="9" class="text-center p-4 text-muted">
					<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ($this->items as $i => $item) :
				$type = [
					'label' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::label((string) ($item->event_type_title ?? '')), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
					'class' => \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper::adminBadgeClass((int) ($item->event_behavior ?? 0)), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
				];

				$dateText = $item->event_date
					? DateOnly::format($item->event_date, 'M j, Y')
					: Text::_('JNONE');

				$timeText = '';
				if (!empty($item->ksu_time))
				{
					$timeText = date('g:i A', strtotime((string) $item->ksu_time));
				}

				$dateTimeText = trim($dateText . ($timeText ? ' ' . $timeText : ''));

				$city  = trim((string) ($item->ksu_city ?? ''));
				$state = trim((string) ($item->ksu_state ?? ''));
				$savedLocationName = trim((string) ($item->ksu_location_name ?? ''));
				$customLocationName = trim((string) ($item->ksu_location_custom ?? ''));

				$locHtml = '';
				if ($city !== '')
				{
					$locHtml = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');

					if ($state !== '')
					{
						$locHtml .= ',&nbsp;' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8');
					}
				}
				elseif ($savedLocationName !== '')
				{
					$locHtml = htmlspecialchars($savedLocationName, ENT_QUOTES, 'UTF-8');
				}
				elseif ($customLocationName !== '')
				{
					$locHtml = htmlspecialchars($customLocationName, ENT_QUOTES, 'UTF-8');
				}
			?>
			<tr>
				<td class="text-center">
					<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
				</td>
				<td>
					<?php echo CheckoutAcl::checkedOutIcon($i, $item, 'events.', $user); ?>
					<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&task=event.edit&id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>
					</a>
					<?php if ($item->road_captain_name) : ?>
						<div class="small text-muted">
							<?php echo Text::_('COM_WENDFOLD_COL_RC'); ?>:
							<?php echo htmlspecialchars($item->road_captain_name, ENT_QUOTES, 'UTF-8'); ?>
						</div>
					<?php endif; ?>
					<?php if ($item->sweep_name) : ?>
						<div class="small text-muted">
							<?php echo Text::_('COM_WENDFOLD_COL_SWEEP'); ?>:
							<?php echo htmlspecialchars($item->sweep_name, ENT_QUOTES, 'UTF-8'); ?>
						</div>
					<?php endif; ?>
				</td>
				<td class="text-center">
					<span class="badge <?php echo htmlspecialchars($type['class'], ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo $type['label']; ?>
					</span>

					<?php $st = $statusLabels[(int) ($item->status ?? 1)] ?? $statusLabels[1]; ?>
					<?php if ((int) ($item->status ?? 1) !== 1) : ?>
						<div class="mt-1">
							<span class="badge <?php echo htmlspecialchars($st['class'], ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8'); ?>
							</span>
						</div>
					<?php endif; ?>
				</td>
				<td class="text-nowrap">
					<?php echo htmlspecialchars($dateTimeText, ENT_QUOTES, 'UTF-8'); ?>
				</td>
				<td class="text-nowrap">
					<?php echo $locHtml !== '' ? $locHtml : '<span class="text-muted">-</span>'; ?>
				</td>
				<td class="text-center text-nowrap">
					<?php if ($item->distance_credit !== null && $item->distance_credit !== '') : ?>
						<?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $item->distance_credit, 1, '.', ''), '0'), '.'), ENT_QUOTES, 'UTF-8'); ?>&nbsp;<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_UNIT_MI'), ENT_QUOTES, 'UTF-8'); ?>
					<?php else : ?>
						<span class="text-muted">-</span>
					<?php endif; ?>
				</td>
				<td class="text-center">
					<?php if ($item->is_riding_event) : ?>
						<span class="icon-check text-success"
							  title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_TIP_HDI_EVENT'), ENT_QUOTES, 'UTF-8'); ?>"></span>
					<?php else : ?>
						<span class="text-muted">-</span>
					<?php endif; ?>
				</td>
				<td class="text-center">
					<?php
					$publishedState = (int) $item->published;
					$publishedLabel = match ($publishedState) {
						1 => 'JPUBLISHED',
						0 => 'JUNPUBLISHED',
						2 => 'JARCHIVED',
						-2 => 'JTRASHED',
						default => 'JUNPUBLISHED',
					};
					$publishedClass = match ($publishedState) {
						1 => 'icon-check-circle text-success',
						0 => 'icon-times-circle text-danger',
						2 => 'icon-archive text-warning',
						-2 => 'icon-trash text-danger',
						default => 'icon-times-circle text-danger',
					};
					?>
					<span class="<?php echo $publishedClass; ?>"
						  title="<?php echo htmlspecialchars(Text::_($publishedLabel), ENT_QUOTES, 'UTF-8'); ?>"
						  aria-label="<?php echo htmlspecialchars(Text::_($publishedLabel), ENT_QUOTES, 'UTF-8'); ?>"></span>
				</td>
				<td class="text-center">
					<a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_wendfold&view=attendance&event_id=' . (int) $item->id, false), ENT_QUOTES, 'UTF-8'); ?>"
					   class="btn btn-sm btn-outline-primary"
					   title="<?php echo htmlspecialchars(Text::_('COM_WENDFOLD_TIP_VIEW_ATTENDANCE'), ENT_QUOTES, 'UTF-8'); ?>">
						<span class="icon-users" aria-hidden="true"></span>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php endif; ?>

	<?php echo $this->pagination->getListFooter(); ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>">
	<input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo HTMLHelper::_('form.token'); ?>

</form>

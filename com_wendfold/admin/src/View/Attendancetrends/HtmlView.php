<?php
/**
 * Wendfold - Event Attendance Trends Report View
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Attendancetrends;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	/** @var object  {date_from, date_to} */
	public object $filters;

	/** @var array<string, int>  Monthly totals keyed by "YYYY-MM" */
	public array $monthlyTotals;

	/** @var array<string, array<int, array>>  Monthly buckets keyed by YYYY-MM, then event_types.id */
	public array $monthlyByType;

	/** @var array  Per-event detail rows */
	public array $eventDetail;

	/** @var object  {overall, by_type[]} */
	public object $averages;

	/** @var integer  Grand total attendance in range */
	public int $grandTotal;

	/** @var integer  Total events with attendance */
	public int $totalEvents;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\AttendancetrendsModel $model */
		$model = $this->getModel();

		$this->filters       = $model->getFilters();
		$this->monthlyTotals = $model->getMonthlyTotals();
		$this->monthlyByType = $model->getMonthlyByType();
		$this->eventDetail   = $model->getEventDetail();
		$this->averages      = $model->getAverages();

		$this->grandTotal  = array_sum($this->monthlyTotals);
		$this->totalEvents = count($this->eventDetail);

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		ToolbarHelper::title(
			Text::_('COM_WENDFOLD_ATTENDANCETRENDS_TITLE'),
			'chart-line'
		);

		$csvUrl = Route::_(
			'index.php?option=com_wendfold&task=attendancetrends.exportcsv'
			. '&date_from=' . urlencode($this->filters->date_from)
			. '&date_to=' . urlencode($this->filters->date_to)
			. '&' . Session::getFormToken() . '=1',
			false
		);

		$toolbar = \Joomla\CMS\Toolbar\Toolbar::getInstance();
		$toolbar->linkButton('download', 'COM_WENDFOLD_BTN_EXPORT_CSV')
			->icon('icon-download')
			->url($csvUrl);

		ToolbarHelper::back(
			'COM_WENDFOLD_SYNC_TITLE',
			'index.php?option=com_wendfold&view=sync'
		);
	}
}

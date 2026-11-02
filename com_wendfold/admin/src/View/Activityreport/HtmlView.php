<?php
/**
 * Wendfold - Chapter Activity Report View
 *
 * Gathers all model data and assigns it to view properties
 * before Joomla renders admin/tmpl/activityreport/default.php.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Activityreport;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBuckets;

class HtmlView extends BaseHtmlView
{
	/** @var object  {date_from, date_to} */
	public object $filters;

	/** @var array  Event type buckets keyed by event_types.id */
	public array $eventsByType;

	/** @var integer  Total attendance records in range */
	public int $totalAttendance;

	/** @var array  Top 10 attendees */
	public array $topAttendees;

	/** @var array  Top 10 riders by miles */
	public array $topRiders;

	/** @var object  {members_paid, total_collected} */
	public object $duesSummary;

	/** @var array  New members in range */
	public array $newMembers;

	/** @var integer  Sum of all event counts */
	public int $totalEvents;

	/**
	 * Display the view.
	 *
	 * @param   string|null  $tpl  Template override name.
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\ActivityreportModel $model */
		$model = $this->getModel();

		$this->filters         = $model->getFilters();
		$this->eventsByType    = $model->getEventsByType();
		$this->totalAttendance = $model->getTotalAttendance();
		$this->topAttendees    = $model->getTopAttendees();
		$this->topRiders       = $model->getTopRiders();
		$this->duesSummary     = $model->getDuesSummary();
		$this->newMembers      = $model->getNewMembers();
		$this->totalEvents     = EventTypeBuckets::total($this->eventsByType);

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar buttons.
	 */
	protected function addToolbar(): void
	{
		ToolbarHelper::title(
			Text::_('COM_WENDFOLD_ACTIVITYREPORT_TITLE'),
			'chart-bar'
		);

		// Export CSV button  -  links to the exportcsv task with CSRF token
		$csvUrl = Route::_(
			'index.php?option=com_wendfold&task=activityreport.exportcsv'
			. '&date_from=' . urlencode($this->filters->date_from)
			. '&date_to=' . urlencode($this->filters->date_to)
			. '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1',
			false
		);

		$toolbar = \Joomla\CMS\Toolbar\Toolbar::getInstance();
		$toolbar->linkButton('download', 'COM_WENDFOLD_BTN_EXPORT_CSV')
			->icon('icon-download')
			->url($csvUrl);

		ToolbarHelper::back('COM_WENDFOLD_SYNC_TITLE', 'index.php?option=com_wendfold&view=sync');
	}
}

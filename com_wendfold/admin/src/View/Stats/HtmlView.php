<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin View: Member Statistics and Leaderboards
 * admin/src/View/Stats/HtmlView.php
 *
 * Fix: Added HTMLHelper::_('bootstrap.tab') so Bootstrap 5 tab JS loads.
 * Without this, clicking tab links highlights the nav item but panels
 * do not switch.
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Stats;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Stats HtmlView
 *
 * Renders the Member Statistics and Leaderboards page with three Bootstrap 5 tabs:
 *   1. Top Miles        - YTD miles leaderboard (top 10)
 *   2. Most Active      - YTD attendance leaderboard (top 10)
 *   3. All Members      - Full paginated member stats table
 */
class HtmlView extends BaseHtmlView
{
	/** @var \Joomla\CMS\Form\Form  Search tools form */
	public $filterForm;

	/** @var array  Active search filters */
	public $activeFilters = [];

	/** @var array  Paginated full member stats (All Members tab) */
	protected $items = [];

	/** @var \Joomla\CMS\Pagination\Pagination  Pagination object */
	protected $pagination;

	/** @var \Joomla\Registry\Registry  Model state */
	protected $state;

	/** @var object|null  Chapter-wide summary totals */
	protected $chapterSummary;

	/** @var array  Top 10 members by YTD miles */
	protected $topMiles = [];

	/** @var array  Top 10 members by YTD attendance */
	protected $topAttendance = [];

	/**
	 * Displays the Stats view.
	 *
	 * @param   string|null  $tpl  Template name override.
	 *
	 * @return  void
	 */
	public function display($tpl = null): void
	{
		// -------------------------------------------------------
		// Bootstrap: load tab component JS.
		// Required for data-bs-toggle="tab" to function.
		// -------------------------------------------------------
		HTMLHelper::_('bootstrap.tab');

		// -------------------------------------------------------
		// Load data from model
		// -------------------------------------------------------
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\StatsModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->filterForm = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();

		$this->topMiles       = $model->getTopMiles(10);
		$this->topAttendance  = $model->getTopAttendance(10);
		$this->chapterSummary = $model->getChapterSummary();

		if (count($errors = $model->getErrors()))
		{
			throw new \Exception(implode("\n", $errors), 500);
		}

		// -------------------------------------------------------
		// Toolbar
		// -------------------------------------------------------
		ToolbarHelper::title(Text::_('COM_WENDFOLD_STATS_TITLE'), 'chart');

		parent::display($tpl);
	}
}

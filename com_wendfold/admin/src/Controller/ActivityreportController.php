<?php
/**
 * Wendfold - Chapter Activity Report Controller
 *
 * Handles:
 *   display  - default task, renders the HTML report
 *   exportcsv - streams the report as a downloadable CSV
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\CsvWriter;

class ActivityreportController extends BaseController
{
	/**
	 * Default task: display the activity report view.
	 *
	 * @param   bool    $cachable   Not used for admin views.
	 * @param   array   $urlparams  Not used for admin views.
	 * @return  static
	 */
	public function display($cachable = false, $urlparams = []): static
	{
		$this->input->set('view', 'activityreport');

		return parent::display($cachable, $urlparams);
	}

	/**
	 * Export the activity report as a CSV file.
	 *
	 * Streams directly to the browser with appropriate headers.
	 * Does not render any HTML view.
	 */
	public function exportcsv(): void
	{
		// CSRF check
		$this->checkToken('get');

		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('core.manage', 'com_wendfold'))
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=activityreport', false));

			return;
		}

		/** @var ActivityreportModel $model */
		$model = $this->getModel('Activityreport');
		$rows  = $model->getExportData();
		$filters = $model->getFilters();

		$filename = 'activity-report-'
			. str_replace('-', '', $filters->date_from)
			. '-to-'
			. str_replace('-', '', $filters->date_to)
			. '.csv';

		// Stream CSV
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Pragma: no-cache');
		header('Expires: 0');

		$out = fopen('php://output', 'w');

		// UTF-8 BOM so Excel opens it correctly
		fputs($out, "\xEF\xBB\xBF");

		foreach ($rows as $row)
		{
			CsvWriter::writeRow($out, $row);
		}

		fclose($out);

		// Terminate  -  do not let Joomla render a layout
		Factory::getApplication()->close();
	}
}

<?php
/**
 * Wendfold - Event Attendance Trends Report Controller
 *
 * Handles:
 *   display    - default task, renders the HTML view
 *   exportcsv  - streams the report as a downloadable CSV
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

class AttendancetrendsController extends BaseController
{
	/**
	 * Default task: display the attendance trends report view.
	 *
	 * @param   bool   $cachable   Not used for admin views.
	 * @param   array  $urlparams  Not used for admin views.
	 *
	 * @return  static
	 *
	 * @since   1.0.0
	 */
	public function display($cachable = false, $urlparams = []): static
	{
		$this->input->set('view', 'attendancetrends');

		return parent::display($cachable, $urlparams);
	}

	/**
	 * Export the attendance trends report as a CSV file.
	 *
	 * Streams directly to the browser; does not render any HTML view.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
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
			$app->redirect(Route::_('index.php?option=com_wendfold&view=attendancetrends', false));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\AttendancetrendsModel $model */
		$model   = $this->getModel('Attendancetrends');
		$rows    = $model->getExportData();
		$filters = $model->getFilters();

		$filename = 'attendance-trends-'
			. str_replace('-', '', $filters->date_from)
			. '-to-'
			. str_replace('-', '', $filters->date_to)
			. '.csv';

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

		// Terminate -- do not let Joomla render a layout
		Factory::getApplication()->close();
	}
}


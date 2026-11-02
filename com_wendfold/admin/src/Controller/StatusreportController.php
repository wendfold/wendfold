<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * Wendfold - Chapter Status Report Controller
 *
 * Handles the statusreport view display and the CSV export action.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       11.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Wendfold\Component\Wendfold\Administrator\Helper\CsvWriter;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

class StatusreportController extends BaseController
{
	/**
	 * Default display task  -  falls through to HtmlView.
	 *
	 * @param   bool   $cachable   Not used for admin views.
	 * @param   array  $urlparams  Not used for admin views.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function display($cachable = false, $urlparams = [])
	{
		return parent::display($cachable, $urlparams);
	}

	/**
	 * Export the expiring members list as a CSV download.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function exportcsv(): void
	{
		// CSRF check
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$app  = $this->app;
		$user = $app->getIdentity();

		if (!$user->authorise('core.manage', 'com_wendfold'))
		{
			$this->setRedirect(
				Route::_('index.php?option=com_wendfold&view=statusreport', false),
				Text::_('JERROR_ALERTNOAUTHOR'),
				'error'
			);
			$this->redirect();

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\StatusreportModel $model */
		$model   = $this->getModel('Statusreport');
		$members = $model->getExpiringMembers();

		$filename = 'member-status-report-' . SiteTime::fromApplication()->today() . '.csv';

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		$out = fopen('php://output', 'w');

		// BOM for Excel UTF-8 compatibility
		fwrite($out, "\xEF\xBB\xBF");

		// Header row
		CsvWriter::writeRow($out, [
			Text::_('COM_WENDFOLD_CSV_MEMBER_NAME'),
			Text::_('COM_WENDFOLD_CSV_MEMBER_NUMBER'),
			Text::_('COM_WENDFOLD_CSV_MEMBERSHIP_TYPE'),
			Text::_('COM_WENDFOLD_CSV_RENEWAL_DATE'),
			Text::_('COM_WENDFOLD_CSV_NATIONAL_VERIFIED'),
			Text::_('COM_WENDFOLD_CSV_REMINDER_60_SENT'),
			Text::_('COM_WENDFOLD_CSV_REMINDER_30_SENT'),
		]);

		foreach ($members as $row)
		{
			CsvWriter::writeRow($out, [
				$row->member_name ?? '',
				$row->member_number ?? '',
				$row->membership_type_label ?? '',
				$row->renewal_date ?? '',
				$row->national_verified ? Text::_('JYES') : Text::_('JNO'),
				$row->reminder_60_sent ?? '',
				$row->reminder_30_sent ?? '',
			]);
		}

		fclose($out);

		// Terminate cleanly  -  no Joomla layout output
		$this->app->close();
	}
}

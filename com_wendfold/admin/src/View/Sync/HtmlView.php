<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin View: National Sync
 * admin/src/View/Sync/HtmlView.php
 *
 * Fix 1: HTMLHelper::_('bootstrap.tab') added so Bootstrap 5 tab JS is loaded.
 * Fix 2: sync_log ORDER BY corrected to sync_date (not 'created' - that column
 *         does not exist on #__wendfold_sync_log).
 *
 * #__wendfold_sync_log columns (verified against schema v1.0.0):
 *   id, sync_type, sync_date, performed_by, records_processed, records_updated,
 *   records_added, records_skipped, records_errored, filename, status, log_detail
 *
 * There is NO 'created' column on this table.
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Sync;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Sync HtmlView
 *
 * Renders the National Sync page with Bootstrap 5 tabs.
 * Loads sync log history, flagged chapter-transfer members, and last sync result.
 */
class HtmlView extends BaseHtmlView
{
	/** @var array  Sync log rows for the History tab */
	protected array $syncLog = [];

	/** @var object|null  Most recent sync_log row */
	protected ?object $lastSync = null;

	/** @var array  Profiles flagged for chapter switch */
	protected array $flaggedMembers = [];

	/** @var array|null  Last CSV preview (headers, mapping, sample rows, errors) */
	protected ?array $importPreview = null;

	/**
	 * Displays the National Sync view.
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
		HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

		$app = Factory::getApplication();

		if ($app instanceof \Joomla\CMS\Application\CMSWebApplicationInterface)
		{
			$preview = $app->getUserState('com_wendfold.sync.import_preview');
			$this->importPreview = is_array($preview) ? $preview : null;
			$app->setUserState('com_wendfold.sync.import_preview', null);
		}

		// -------------------------------------------------------
		// Data: sync log history
		// ORDER BY sync_date  -  NOT 'created' (no such column).
		// -------------------------------------------------------
		/** @var DatabaseInterface $db */
		$db = Factory::getContainer()->get(DatabaseInterface::class);

		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_sync_log'))
			->order($db->quoteName('sync_date') . ' DESC')
			->setLimit(20);
		$db->setQuery($query);
		$this->syncLog  = $db->loadObjectList() ?: [];
		$this->lastSync = !empty($this->syncLog) ? $this->syncLog[0] : null;

		// -------------------------------------------------------
		// Data: members flagged for chapter switch
		// -------------------------------------------------------
		$query = $db->createQuery()
			->select([
				$db->quoteName('p.id'),
				$db->quoteName('p.member_number'),
				$db->quoteName('p.chapter_on_file'),
				$db->quoteName('u.name'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'LEFT',
				$db->quoteName('#__users', 'u')
					. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('p.chapter_switch_flagged') . ' = 1')
			->order($db->quoteName('u.name') . ' ASC');
		$db->setQuery($query);
		$this->flaggedMembers = $db->loadObjectList() ?: [];

		// -------------------------------------------------------
		// Toolbar
		// -------------------------------------------------------
		ToolbarHelper::title(Text::_('COM_WENDFOLD_SYNC_PAGE_TITLE'), 'sync');
		$this->getDocument()->getWebAssetManager()->useScript('com_wendfold.confirm');

		parent::display($tpl);
	}
}

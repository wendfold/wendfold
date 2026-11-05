<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Site\View\Events;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	protected $items;

	protected $pagination;

	protected $state;

	/**
	 * Published event types for the filter.
	 *
	 * @var    array
	 */
	protected $eventTypes = [];

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Site\Model\EventsModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$this->eventTypes = $db->setQuery(
			$db->createQuery()
				->select([$db->quoteName('id'), $db->quoteName('title')])
				->from($db->quoteName('#__wendfold_event_types'))
				->where($db->quoteName('published') . ' = 1')
				->order($db->quoteName('ordering') . ' ASC')
		)->loadObjectList() ?? [];

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		parent::display($tpl);
	}
}

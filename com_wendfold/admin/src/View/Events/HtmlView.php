<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Events List View
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Events;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

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
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EventsModel $model */
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

		$this->addToolbar();
		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		$canDo = ContentHelper::getActions('com_wendfold');
		$user = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance();

		ToolbarHelper::title(Text::_('COM_WENDFOLD_EVENTS_TITLE'), 'calendar');

		$toolbar->addNew('event.add');
		$toolbar->divider();
		$toolbar->publish('events.publish');
		$toolbar->unpublish('events.unpublish');
		$toolbar->archive('events.archive');
		CheckoutAcl::addCheckinButton($toolbar, $user, 'events.checkin');
		$toolbar->divider();

		if ($canDo->get('core.edit.state'))
		{
			$toolbar->trash('events.trash');
		}

		if ($canDo->get('core.delete'))
		{
			$toolbar->delete('events.delete')
				->message('COM_WENDFOLD_CONFIRM_DELETE_EVENTS');
		}

		$toolbar->divider();
		$toolbar->preferences('com_wendfold');
	}
}

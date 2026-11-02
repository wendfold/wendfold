<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\View\Locations;

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

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\LocationsModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();

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
		ToolbarHelper::title(Text::_('COM_WENDFOLD_LOCATIONS_TITLE'), 'map-marker');
		$toolbar->addNew('location.add');
		$toolbar->divider();
		$toolbar->publish('locations.publish');
		$toolbar->unpublish('locations.unpublish');
		CheckoutAcl::addCheckinButton($toolbar, $user, 'locations.checkin');
		$toolbar->divider();

		if ($canDo->get('core.delete'))
		{
			$toolbar->delete('locations.delete')->message('COM_WENDFOLD_CONFIRM_DELETE_LOCATIONS');
		}
	}
}

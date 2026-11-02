<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * Officers list view
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Officers;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

/**
 * View class for a list of officers.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * An array of items
	 *
	 * @var  array
	 * @since  1.0.0
	 */
	protected $items;

	/**
	 * The pagination object
	 *
	 * @var  \Joomla\CMS\Pagination\Pagination
	 * @since  1.0.0
	 */
	protected $pagination;

	/**
	 * The model state
	 *
	 * @var  \Joomla\Registry\Registry
	 * @since  1.0.0
	 */
	protected $state;

	/**
	 * Form object for search filters
	 *
	 * @var  \Joomla\CMS\Form\Form
	 * @since  1.0.0
	 */
	public $filterForm;

	/**
	 * The active search filters
	 *
	 * @var  array
	 * @since  1.0.0
	 */
	public $activeFilters = [];


	/**
	 * Display the view.
	 *
	 * @param   string  $tpl  The name of the template file to parse.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @throws  \Exception
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\OfficersModel $model */
		$model = $this->getModel();

		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->state         = $model->getState();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();

		if (count($errors = $model->getErrors()))
		{
			throw new \Exception(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		parent::display($tpl);
	}


	/**
	 * Add the page title and toolbar  -  Joomla 6 compatible
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function addToolbar(): void
	{
		// Set document title (required in Joomla 5/6)
		$this->document->setTitle(Text::_('COM_WENDFOLD_OFFICERS_TOOLBAR_TITLE'));

		$canDo = ContentHelper::getActions('com_wendfold');
		$user = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance('toolbar');

		// New button (standard Joomla 5/6 way)
		$toolbar->addNew('officer.add');
		CheckoutAcl::addCheckinButton($toolbar, $user, 'officers.checkin');

		$toolbar->divider();

		if ($canDo->get('core.delete'))
		{
			$toolbar->delete('officers.delete')
				->message(Text::_('COM_WENDFOLD_CONFIRM_DELETE_OFFICERS'));
		}

		// Optional: if you want the old-school icon+title look in the heading area
		// (still works in Joomla 6 for backward compatibility)
		ToolbarHelper::title(Text::_('COM_WENDFOLD_OFFICERS_TOOLBAR_TITLE'), 'users');
	}
}

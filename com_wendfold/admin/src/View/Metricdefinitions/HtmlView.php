<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Metricdefinitions;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

/**
 * Metric definition list.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var    array
	 * @since  1.0.0
	 */
	protected $items;

	/**
	 * @var    \Joomla\CMS\Pagination\Pagination
	 * @since  1.0.0
	 */
	protected $pagination;

	/**
	 * @var    \Joomla\Registry\Registry
	 * @since  1.0.0
	 */
	protected $state;

	/**
	 * @var    array
	 * @since  1.0.0
	 */
	protected $lookup = [];

	/**
	 * @param   string  $tpl  Layout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MetricdefinitionsModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->lookup = [
			'title'    => 'COM_WENDFOLD_METRICS_TITLE',
			'itemTask' => 'metricdefinition',
			'listTask' => 'metricdefinitions',
			'view'     => 'metricdefinitions',
			'empty'    => 'COM_WENDFOLD_METRICS_EMPTY',
			'columns'  => [
				[
					'heading' => 'COM_WENDFOLD_METRIC_KEY_LABEL',
					'sort'    => 'a.metric_key',
					'field'   => 'metric_key',
					'kind'    => 'text',
				],
				[
					'heading' => 'COM_WENDFOLD_METRIC_UNIT_LABEL',
					'sort'    => 'a.unit_label',
					'field'   => 'unit_label',
					'kind'    => 'lang',
				],
				[
					'heading' => 'COM_WENDFOLD_METRIC_AGGREGATION_LABEL',
					'sort'    => 'a.aggregation',
					'field'   => 'aggregation',
					'kind'    => 'aggregation',
				],
				[
					'heading' => 'COM_WENDFOLD_METRIC_ALLOW_EVENT_LABEL',
					'sort'    => 'a.allow_event_credit',
					'field'   => 'allow_event_credit',
					'kind'    => 'yesno',
				],
				[
					'heading' => 'COM_WENDFOLD_METRIC_ALLOW_MEMBER_LABEL',
					'sort'    => 'a.allow_member_entry',
					'field'   => 'allow_member_entry',
					'kind'    => 'yesno',
				],
				[
					'heading' => 'COM_WENDFOLD_METRIC_DASHBOARD_LABEL',
					'sort'    => 'a.show_on_dashboard',
					'field'   => 'show_on_dashboard',
					'kind'    => 'yesno',
				],
			],
		];

		if (\count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function addToolbar(): void
	{
		$user = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance();
		ToolbarHelper::title(Text::_('COM_WENDFOLD_METRICS_TITLE'), 'chart');

		if ($user->authorise('core.create', 'com_wendfold'))
		{
			$toolbar->addNew('metricdefinition.add');
		}

		if ($user->authorise('core.edit.state', 'com_wendfold'))
		{
			$toolbar->publish('metricdefinitions.publish');
			$toolbar->unpublish('metricdefinitions.unpublish');
		}

		CheckoutAcl::addCheckinButton($toolbar, $user, 'metricdefinitions.checkin');

		if ($user->authorise('core.delete', 'com_wendfold'))
		{
			$toolbar->delete('metricdefinitions.delete')->message('COM_WENDFOLD_LOOKUP_CONFIRM_DELETE');
		}
	}
}

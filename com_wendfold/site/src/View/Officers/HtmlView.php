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
 * @since       9.0.0
 */

namespace Wendfold\Component\Wendfold\Site\View\Officers;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Front-end Officers list view.
 *
 * @since  9.0.0
 */
class HtmlView extends BaseHtmlView
{
	/** @var array */
	protected $items;

	/** @var array */
	protected $historical;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Site\Model\OfficersModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->historical = $model->getHistoricalOfficers();

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->document->setTitle('Chapter Officers');

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		parent::display($tpl);
	}
}

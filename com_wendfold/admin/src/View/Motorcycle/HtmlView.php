<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin View: Motorcycle Edit
 * admin/src/View/Motorcycle/HtmlView.php
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Motorcycle;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
	/** @var \Joomla\CMS\Form\Form */
	protected $form;

	/** @var object */
	protected $item;

	/** @var \Joomla\Registry\Registry */
	protected $state;

	/** @var string  Member name for display in the heading */
	protected $memberName = '';

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MotorcycleModel $model */
		$model = $this->getModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();
		$this->state = $model->getState();

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		// Load the member name for context in the heading
		$profileId = (int) ($this->item->profile_id ?? 0);

		if ($profileId > 0)
		{
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->createQuery()
				->select('u.name')
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->where('p.id = ' . $profileId);

			$this->memberName = $db->setQuery($query)->loadResult() ?? '';
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		Factory::getApplication()->getInput()->set('hidemainmenu', true);

		$isNew      = empty($this->item->id);
		$profileId  = (int) ($this->item->profile_id ?? 0);
		$toolbar    = Toolbar::getInstance();

		ToolbarHelper::title(
			$isNew
				? Text::_('COM_WENDFOLD_MOTORCYCLE_NEW_TITLE')
				: Text::_('COM_WENDFOLD_MOTORCYCLE_EDIT_TITLE'),
			'motorcycle'
		);

		$toolbar->apply('motorcycle.apply');
		$toolbar->save('motorcycle.save');
		$toolbar->divider();

		// Cancel goes back to the member edit form if we know the profile
		if ($profileId > 0)
		{
			$toolbar->cancel('motorcycle.cancel', 'JTOOLBAR_CANCEL');
		}
		else
		{
			$toolbar->cancel('motorcycle.cancel', 'JTOOLBAR_CANCEL');
		}
	}
}

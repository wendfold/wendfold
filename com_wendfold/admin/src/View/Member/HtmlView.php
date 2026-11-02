<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin View: Member Edit
 * admin/src/View/Member/HtmlView.php
 *
 * Changelog:
 *   Polish: Loads motorcycles for this member so the Motorcycles tab
 *           can display them inline without a separate page.
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Member;

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

	/**
	 * Motorcycles belonging to this member.
	 * Empty array for new (unsaved) members.
	 *
	 * @var array
	 */
	protected $motorcycles = [];

	/**
	 * Display the member edit form.
	 *
	 * @param   string|null  $tpl  Template override.
	 * @return  void
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MemberModel $model */
		$model = $this->getModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();
		$this->state = $model->getState();

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		// Load motorcycles for existing members only
		if (!empty($this->item->id))
		{
			$this->motorcycles = $this->loadMotorcycles((int) $this->item->id);
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * Fetch all motorcycles for a given profile ID.
	 *
	 * @param   int  $profileId
	 * @return  array
	 */
	protected function loadMotorcycles(int $profileId): array
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);

		$query = $db->createQuery()
			->select(['id', 'year', 'make', 'model', 'color', 'nickname', 'vin', 'is_primary', 'published'])
			->from($db->quoteName('#__wendfold_motorcycles'))
			->where($db->quoteName('profile_id') . ' = ' . $profileId)
			->order('is_primary DESC, year DESC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Build the admin toolbar.
	 *
	 * @return  void
	 */
	protected function addToolbar(): void
	{
		Factory::getApplication()->getInput()->set('hidemainmenu', true);

		$isNew   = ($this->item->id == 0);
		$toolbar = Toolbar::getInstance();

		ToolbarHelper::title(
			$isNew
				? Text::_('COM_WENDFOLD_MEMBER_NEW_TITLE')
				: Text::_('COM_WENDFOLD_MEMBER_EDIT_TITLE'),
			'user'
		);

		$toolbar->apply('member.apply');
		$toolbar->save('member.save');
		$toolbar->save2new('member.save2new');
		$toolbar->divider();

		if ($isNew)
		{
			$toolbar->cancel('member.cancel');
		}
		else
		{
			$toolbar->cancel('member.cancel', 'JTOOLBAR_CLOSE');
		}
	}
}

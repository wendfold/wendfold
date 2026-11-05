<?php
/**
 * Wendfold - Administrator Member Controller
 *
 * Member photos are edited on the site, not in this administrator save.
 * A posted photo value is dropped so the parent save cannot store it.
 * A posted id that does not match the record being saved is refused first.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\MemberSaveInput;
use Wendfold\Component\Wendfold\Administrator\Helper\PostGuard;

class MemberController extends FormController
{
	/** @var string Redirect to members list after cancel or save */
	protected $view_list = 'members';

	/** @var string Edit view name */
	protected $view_item = 'member';

	/**
	 * Save a member without accepting a photo upload or a forged id.
	 *
	 * Flow:
	 *   1. Require POST and a valid CSRF token.
	 *   2. Allow edit for an existing record, or add for a new one.
	 *   3. Refuse a posted jform id that is not the record being saved.
	 *   4. Drop posted photo fields, then delegate to parent::save().
	 *
	 * @param   string|null  $key     Primary key field name.
	 * @param   string|null  $urlVar  URL variable name.
	 *
	 * @return  void
	 */
	public function save($key = null, $urlVar = null): void
	{
		$app = Factory::getApplication();

		// Stop before FormController::save() checks the token.
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		// Same record id FormController::save() authorizes.
		$id = (int) $app->getInput()->getInt($urlVar ?: 'id');

		if ($id)
		{
			if (!$this->allowEdit(['id' => $id], 'id'))
			{
				$this->redirectDenied('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED');

				return;
			}
		}
		elseif (!$this->allowAdd())
		{
			$this->redirectDenied('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED');

			return;
		}

		$jform = $app->getInput()->post->get('jform', [], 'array');

		// A posted id for a different member is refused for every user.
		if (MemberSaveInput::idMismatches($id, $jform))
		{
			$this->redirectDenied('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED');

			return;
		}

		$app->getInput()->post->set('jform', MemberSaveInput::withoutPhoto($jform, $id));

		parent::save($key, $urlVar);
	}

	/**
	 * Redirect to the member list when this save is not permitted.
	 *
	 * @param   string  $message  Language key for the error shown on the list.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function redirectDenied(string $message): void
	{
		$this->setMessage(Text::_($message), 'error');
		$this->setRedirect(
			Route::_(
				'index.php?option=com_wendfold&view=members'
				. $this->getRedirectToListAppend(),
				false
			)
		);
	}
}

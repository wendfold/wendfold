<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin Controller: Motorcycle (single record)
 * admin/src/Controller/MotorcycleController.php
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * MotorcycleController
 *
 * Handles save, apply, cancel for a single motorcycle record.
 * All method implementations are inherited from FormController.
 *
 * After save/cancel, redirects back to the member edit page
 * so the admin lands back on the Motorcycles tab.
 */
class MotorcycleController extends FormController
{
	protected $view_list = 'members';

	/**
	 * Save, then return to the member edit screen when a profile id is known.
	 *
	 * Joomla 6.0 builds the list URL as
	 * `'&view=' . $this->view_list . $this->getRedirectToListAppend()`,
	 * evaluating view_list before the append runs. Retarget view_list first.
	 *
	 * @param   string  $key     Primary key name.
	 * @param   string  $urlVar  URL variable name.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function save($key = null, $urlVar = null)
	{
		$this->prepareMemberEditListRedirect();

		return parent::save($key, $urlVar);
	}

	/**
	 * Cancel, then return to the member edit screen when a profile id is known.
	 *
	 * @param   string  $key  Primary key name.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function cancel($key = null)
	{
		$this->prepareMemberEditListRedirect();

		return parent::cancel($key);
	}

	/**
	 * Point the FormController list redirect at member edit before URL build.
	 *
	 * Must run before parent::save()/cancel() on Joomla 6.0 because the
	 * left-to-right concatenation reads $view_list before getRedirectToListAppend().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function prepareMemberEditListRedirect(): void
	{
		if ($this->profileIdFromRequest() > 0)
		{
			$this->view_list = 'member'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		}
	}

	/**
	 * Joomla 6.1+ list redirect hook: one clean member-edit URL.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function getRedirectUrlToList(): string
	{
		$profileId = $this->profileIdFromRequest();

		if ($profileId > 0)
		{
			return Route::_(self::memberEditRedirectUrl($profileId), false);
		}

		// Do not call parent::getRedirectUrlToList(); it exists only from 6.1.
		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		$listView = $this->view_list;

		return Route::_(
			'index.php?option=' . $this->option . '&view=' . $listView
				. $this->getRedirectToListAppend(),
			false
		);
	}

	/**
	 * Append layout and id for the member-edit list redirect.
	 *
	 * Does not set view_list: Joomla 6.0 already read that property before
	 * calling this method. prepareMemberEditListRedirect() must run first.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function getRedirectToListAppend()
	{
		$profileId = $this->profileIdFromRequest();

		if ($profileId > 0)
		{
			return self::memberEditListAppend($profileId) . parent::getRedirectToListAppend();
		}

		return parent::getRedirectToListAppend();
	}

	/**
	 * Member edit route used after motorcycle save or cancel.
	 *
	 * @param   int  $profileId  Member profile id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function memberEditRedirectUrl(int $profileId): string
	{
		return 'index.php?option=com_wendfold&view=member&layout=edit&id=' . $profileId;
	}

	/**
	 * Query suffix paired with view_list=member for the 6.0 append path.
	 *
	 * @param   int  $profileId  Member profile id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function memberEditListAppend(int $profileId): string
	{
		return '&layout=edit&id=' . $profileId;
	}

	/**
	 * Read the member profile id from the motorcycle form request.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	protected function profileIdFromRequest(): int
	{
		$input     = $this->app->getInput();
		$profileId = $input->getInt('jform_profile_id', 0);

		if ($profileId > 0)
		{
			return $profileId;
		}

		$jform = $input->post->get('jform', [], 'array');

		return (int) ($jform['profile_id'] ?? 0);
	}
}

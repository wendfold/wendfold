<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\User\User;

/**
 * ACL helpers for list check-in, matching Joomla core list toolbars.
 *
 * @since  1.0.0
 */
final class CheckoutAcl
{
	/**
	 * Whether the user may use the list Check-in toolbar action.
	 *
	 * @param   User  $user  Current user.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function canManageCheckin(User $user): bool
	{
		return $user->authorise('core.admin', 'com_wendfold')
			|| $user->authorise('core.manage', 'com_checkin');
	}

	/**
	 * Whether the user may clear a specific row's checkout lock.
	 *
	 * @param   User     $user        Current user.
	 * @param   integer  $checkedOut  User id holding the lock, or 0/null when free.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function canCheckinItem(User $user, int $checkedOut): bool
	{
		if ($checkedOut < 1)
		{
			return false;
		}

		return self::canManageCheckin($user) || $checkedOut === (int) $user->id;
	}

	/**
	 * Add the Check-in toolbar button when the user is allowed.
	 *
	 * @param   Toolbar  $toolbar  Admin toolbar.
	 * @param   User     $user     Current user.
	 * @param   string   $task     Task such as events.checkin.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function addCheckinButton(Toolbar $toolbar, User $user, string $task): void
	{
		if (!self::canManageCheckin($user))
		{
			return;
		}

		$toolbar->checkin($task);
	}

	/**
	 * HTML for jgrid.checkedout in a title cell, or an empty string when free.
	 *
	 * @param   integer  $index     Row index for the grid.
	 * @param   object   $item      List row with checked_out, checked_out_time, editor.
	 * @param   string   $listTask  Prefix with trailing dot, e.g. events.
	 * @param   User     $user      Current user.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function checkedOutIcon(int $index, object $item, string $listTask, User $user): string
	{
		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Mirrors checked_out.
		$checkedOut = (int) ($item->checked_out ?? 0);

		if ($checkedOut < 1)
		{
			return '';
		}

		$prefix = str_ends_with($listTask, '.') ? $listTask : $listTask . '.';

		return (string) HTMLHelper::_(
			'jgrid.checkedout',
			$index,
			(string) ($item->editor ?? ''),
			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Mirrors checked_out_time.
			$item->checked_out_time ?? '',
			$prefix,
			self::canCheckinItem($user, $checkedOut)
		);
	}
}

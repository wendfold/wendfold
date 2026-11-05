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

use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

/**
 * Per-member renewal reminder opt-out stored in #__users.params.
 *
 * No profile schema change: the flag lives on the linked Joomla user
 * under a flat params key so upgraded sites need no DDL.
 *
 * @since  1.0.0
 */
final class RenewalReminderOptOut
{
	/**
	 * Flat key in #__users.params (no dots — avoids nested Registry paths).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const PARAM_KEY = 'wendfold_renewal_reminders_optout';

	/**
	 * Whether the user has opted out of renewal reminder emails.
	 *
	 * @param   User  $user  Joomla user.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isOptedOut(User $user): bool
	{
		return self::isOptedOutValue($user->getParam(self::PARAM_KEY, 0));
	}

	/**
	 * Read the flag from a params JSON string or Registry.
	 *
	 * @param   mixed  $params  Registry, JSON string, array, or null.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isOptedOutFromParams(mixed $params): bool
	{
		if ($params instanceof Registry)
		{
			return self::isOptedOutValue($params->get(self::PARAM_KEY, 0));
		}

		if (\is_array($params))
		{
			return self::isOptedOutValue($params[self::PARAM_KEY] ?? 0);
		}

		if (!\is_string($params) || trim($params) === '')
		{
			return false;
		}

		$registry = new Registry($params);

		return self::isOptedOutValue($registry->get(self::PARAM_KEY, 0));
	}

	/**
	 * Form field name for the admin/site opt-out switch.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const FORM_FIELD = 'renewal_reminders_optout';

	/**
	 * Site profile-edit user-state key for posted form data.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const SITE_FORM_STATE = 'com_wendfold.edit.profileedit.data';

	/**
	 * Admin member-edit user-state key (Joomla FormController context).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const ADMIN_FORM_STATE = 'com_wendfold.edit.member.data';

	/**
	 * Opt-out value to show on a form: prefer posted/session over stored.
	 *
	 * Does not write user params. Stored opt-out stays unchanged until save.
	 *
	 * @param   mixed    $formState     User-state form data, or null when absent.
	 * @param   boolean  $storedOptOut  Value from #__users.params.
	 *
	 * @return  integer  1 or 0 for the checkbox/switch.
	 *
	 * @since   1.0.0
	 */
	public static function resolveFormOptOut(mixed $formState, bool $storedOptOut): int
	{
		$field = self::FORM_FIELD;

		if (\is_array($formState) && \array_key_exists($field, $formState))
		{
			return (int) $formState[$field] === 1 ? 1 : 0;
		}

		if (\is_object($formState))
		{
			$attrs = get_object_vars($formState);

			if (\array_key_exists($field, $attrs))
			{
				return (int) $attrs[$field] === 1 ? 1 : 0;
			}
		}

		return $storedOptOut ? 1 : 0;
	}

	/**
	 * Put the stored opt-out on form data only when the field is absent.
	 *
	 * Session redisplay after a validation error already carries the posted
	 * value; overwriting it from the user would drop the admin's change.
	 *
	 * @param   mixed    $data     Form data (array or object).
	 * @param   boolean  $optedOut Stored opt-out from the linked user.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public static function fillFormDataIfMissing(mixed $data, bool $optedOut): mixed
	{
		$field = self::FORM_FIELD;
		$value = $optedOut ? 1 : 0;

		if (\is_array($data))
		{
			if (\array_key_exists($field, $data))
			{
				return $data;
			}

			$data[$field] = $value;

			return $data;
		}

		if (\is_object($data))
		{
			$attrs = get_object_vars($data);

			if (\array_key_exists($field, $attrs))
			{
				return $data;
			}

			$data->$field = $value;
		}

		return $data;
	}

	/**
	 * Set or clear the opt-out flag on a user and persist params.
	 *
	 * @param   User     $user    User to update.
	 * @param   boolean  $optOut  True to stop renewal reminder emails.
	 *
	 * @return  boolean  False when the user could not be saved.
	 *
	 * @since   1.0.0
	 */
	public static function setOptedOut(User $user, bool $optOut): bool
	{
		if ((int) $user->id < 1)
		{
			return false;
		}

		$user->setParam(self::PARAM_KEY, $optOut ? 1 : 0);

		return (bool) $user->save(true);
	}

	/**
	 * Normalise stored values to a boolean.
	 *
	 * @param   mixed  $value  Params value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function isOptedOutValue(mixed $value): bool
	{
		if (\is_bool($value))
		{
			return $value;
		}

		if (\is_int($value) || \is_float($value))
		{
			return (int) $value === 1;
		}

		if (\is_string($value))
		{
			$trimmed = strtolower(trim($value));

			return $trimmed === '1' || $trimmed === 'true' || $trimmed === 'yes';
		}

		return false;
	}
}

<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\Registry\Registry;

/**
 * Reads model state with Registry empty-value semantics.
 *
 * Joomla\CMS\MVC\Model\State::get() emits a deprecation when a stored value is
 * empty and returns that empty value. In Joomla 7 Registry behaviour wins: an
 * empty stored value yields the supplied default. Call this helper instead of
 * State::get() / Model::getState($path, $default) so Wendfold stays quiet on
 * 6.2 and matches 7.0.
 *
 * @since  1.0.0
 */
final class StateValue
{
	/**
	 * Read one state path without triggering State::get()'s empty-value deprecation.
	 *
	 * @param   Registry    $state    Model state (CMS\MVC\Model\State extends Registry).
	 * @param   string      $path     State key (null separator, e.g. filter.search).
	 * @param   mixed|null  $default  Value when the key is missing or empty.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public static function get(Registry $state, string $path, mixed $default = null): mixed
	{
		$data = $state->toArray();

		if (!\array_key_exists($path, $data))
		{
			return $default;
		}

		$value = $data[$path];

		// Match Registry::get() with a null separator: empty string / null → default.
		if ($value === '' || $value === null)
		{
			return $default;
		}

		return $value;
	}
}

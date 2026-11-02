<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

/**
 * Rejects state-changing requests that are not an HTTP POST with a valid CSRF token.
 *
 * Session::checkToken() treats a matching X-CSRF-Token header as success on every
 * verb, so the request method is checked first.
 *
 * @since  1.0.0
 */
final class PostGuard
{
	/**
	 * Whether the current request is a POST carrying a valid CSRF token.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function passes(): bool
	{
		$app = Factory::getApplication();

		if (strtoupper($app->getInput()->getMethod()) !== 'POST')
		{
			return false;
		}

		return Session::checkToken('post');
	}
}

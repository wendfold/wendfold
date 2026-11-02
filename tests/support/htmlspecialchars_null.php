<?php

/**
 * Weak-typed shim that mirrors Joomla templates (no strict_types).
 *
 * Passing null into htmlspecialchars() raises E_DEPRECATED on PHP 8.1+
 * under weak typing — the attendance #111 deprecation flood on PHP 8.5.
 *
 * Returns true when that deprecation was observed.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

$sawDeprecation = false;

set_error_handler(
	static function ($errno, $errstr) use (&$sawDeprecation) {
		if ($errno === E_DEPRECATED && strpos($errstr, 'htmlspecialchars') !== false)
		{
			$sawDeprecation = true;
		}

		return true;
	}
);

try
{
	htmlspecialchars(null, ENT_QUOTES, 'UTF-8');
}
finally
{
	restore_error_handler();
}

return $sawDeprecation;

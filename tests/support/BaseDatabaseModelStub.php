<?php

/**
 * Wendfold - BaseDatabaseModel stand-in for site model unit tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\Model;

use Joomla\Registry\Registry;

/**
 * Minimal BaseDatabaseModel so site models can load without Joomla.
 *
 * @since  1.0.0
 */
class BaseDatabaseModel
{
	/**
	 * @var    Registry
	 * @since  1.0.0
	 */
	private Registry $state;

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private array $errors = [];

	/**
	 * @param   array  $config  Unused.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		$this->state = new Registry;
	}

	/**
	 * @param   string  $error  Message.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setError($error): void
	{
		$this->errors[] = (string) $error;
	}

	/**
	 * @return  string|false
	 *
	 * @since   1.0.0
	 */
	public function getError()
	{
		if ($this->errors === [])
		{
			return false;
		}

		return $this->errors[\array_key_last($this->errors)];
	}

	/**
	 * @param   string  $key    State key.
	 * @param   mixed   $value  Value.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function setState($key, $value = null)
	{
		$this->state->set((string) $key, $value);

		return $value;
	}

	/**
	 * @param   string|null  $key      State key.
	 * @param   mixed        $default  Default.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function getState($key = null, $default = null)
	{
		if ($key === null)
		{
			return $this->state;
		}

		return $this->state->get((string) $key, $default);
	}
}

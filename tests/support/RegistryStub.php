<?php

/**
 * Wendfold - Minimal Joomla Registry for unit tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Registry;

if (!\class_exists(Registry::class, false))
{
	/**
	 * Flat get/set Registry used by RenewalReminderOptOut tests.
	 *
	 * @since  1.0.0
	 */
	class Registry
	{
		/**
		 * Stored values.
		 *
		 * @var    array<string, mixed>
		 * @since  1.0.0
		 */
		private array $data = [];

		/**
		 * @param   mixed  $data  JSON string, array, or null.
		 *
		 * @since   1.0.0
		 */
		public function __construct(mixed $data = null)
		{
			if (\is_string($data) && $data !== '')
			{
				$decoded = json_decode($data, true);
				$this->data = \is_array($decoded) ? $decoded : [];

				return;
			}

			if (\is_array($data))
			{
				$this->data = $data;
			}
		}

		/**
		 * @param   string  $key      Param key.
		 * @param   mixed   $default  Default.
		 *
		 * @return  mixed
		 *
		 * @since   1.0.0
		 */
		public function get($key, $default = null)
		{
			return $this->data[$key] ?? $default;
		}

		/**
		 * @param   string  $key    Param key.
		 * @param   mixed   $value  Value.
		 *
		 * @return  self
		 *
		 * @since   1.0.0
		 */
		public function set($key, $value)
		{
			$this->data[$key] = $value;

			return $this;
		}

		/**
		 * @return  array<string, mixed>
		 *
		 * @since   1.0.0
		 */
		public function toArray(): array
		{
			return $this->data;
		}
	}
}


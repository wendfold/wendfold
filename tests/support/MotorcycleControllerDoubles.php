<?php

/**
 * Wendfold - stubs so MotorcycleController can load under PHPUnit.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\Controller
{
	/**
	 * Minimal FormController stand-in for motorcycle redirect tests.
	 *
	 * Mimics Joomla 6.0 (append-only) and 6.1+ (getRedirectUrlToList) callers.
	 *
	 * @since  1.0.0
	 */
	class FormController
	{
		/** @var object|null */
		protected $app;

		/** @var string */
		protected $option = 'com_wendfold';

		/**
		 * FormController list view segment.
		 *
		 * @var    string
		 * @since  1.0.0
		 */
		protected $view_list = ''; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

		/**
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		protected function getRedirectUrlToList(): string
		{
			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
			$listView = $this->view_list;

			return 'index.php?option=' . $this->option . '&view=' . $listView
				. $this->getRedirectToListAppend();
		}

		/**
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		protected function getRedirectToListAppend()
		{
			return '';
		}

		/**
		 * @param   string|null  $key     Primary key name.
		 * @param   string|null  $urlVar  URL variable name.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function save($key = null, $urlVar = null)
		{
			return true;
		}

		/**
		 * @param   string|null  $key  Primary key name.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function cancel($key = null)
		{
			return true;
		}
	}
}

namespace Joomla\CMS\Router
{
	/**
	 * Identity Route::_ for unit tests.
	 *
	 * @since  1.0.0
	 */
	class Route
	{
		/**
		 * @param   string   $url    Internal URL.
		 * @param   boolean  $xhtml  Unused.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public static function _($url, $xhtml = true): string // phpcs:ignore Joomla.NamingConventions.ValidFunctionName.MethodUnderscore
		{
			return (string) $url;
		}
	}
}

namespace Wendfold\Tests\Support
{
	/**
	 * Input stand-in for motorcycle redirect tests.
	 *
	 * @since  1.0.0
	 */
	final class MotorcycleInputDouble
	{
		/** @var object */
		public object $post;

		/**
		 * @param   integer              $profileId  jform_profile_id query value.
		 * @param   array<string,mixed>  $jform      Posted jform array.
		 *
		 * @since   1.0.0
		 */
		public function __construct(private int $profileId = 0, array $jform = [])
		{
			$this->post = new class ($jform)
			{
				/**
				 * @param   array<string,mixed>  $jform  Posted jform.
				 */
				public function __construct(private array $jform)
				{
				}

				/**
				 * @param   string  $name     Field name.
				 * @param   mixed   $default  Default.
				 * @param   string  $filter   Filter name.
				 *
				 * @return  mixed
				 */
				public function get(string $name, $default = null, string $filter = 'cmd')
				{
					return $name === 'jform' ? $this->jform : $default;
				}
			};
		}

		/**
		 * @param   string   $name     Field name.
		 * @param   integer  $default  Default.
		 *
		 * @return  integer
		 *
		 * @since   1.0.0
		 */
		public function getInt(string $name, int $default = 0): int
		{
			return $name === 'jform_profile_id' ? $this->profileId : $default;
		}
	}

	/**
	 * Application stand-in exposing getInput().
	 *
	 * @since  1.0.0
	 */
	final class MotorcycleAppDouble
	{
		/**
		 * @param   MotorcycleInputDouble  $input  Request input.
		 *
		 * @since   1.0.0
		 */
		public function __construct(private MotorcycleInputDouble $input)
		{
		}

		/**
		 * @return  MotorcycleInputDouble
		 *
		 * @since   1.0.0
		 */
		public function getInput(): MotorcycleInputDouble
		{
			return $this->input;
		}
	}
}

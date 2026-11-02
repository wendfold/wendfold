<?php

/**
 * Wendfold - stubs so ListController can run under PHPUnit.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\Controller
{
	/**
	 * Minimal AdminController stand-in for list batch message tests.
	 *
	 * @since  1.0.0
	 */
	class AdminController
	{
		/** @var object|null */
		protected $app;

		/** @var string */
		protected $option = 'com_wendfold';

		/** @var string */
		protected $text_prefix = 'COM_WENDFOLD'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

		/** @var string */
		protected $view_list = 'events'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

		/** @var string */
		private string $task = 'publish';

		/**
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		protected function checkToken(): void
		{
		}

		/**
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		protected function getTask(): string
		{
			return $this->task;
		}

		/**
		 * @param   string  $task  Active task name.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function setTaskForTest(string $task): void
		{
			$this->task = $task;
		}

		/**
		 * @param   string  $name    Model name.
		 * @param   string  $prefix  Class prefix.
		 * @param   array   $config  Model config.
		 *
		 * @return  object
		 *
		 * @since   1.0.0
		 */
		public function getModel($name = '', $prefix = '', $config = [])
		{
			return new \Wendfold\Tests\Support\ListModelDouble;
		}

		/**
		 * @return  object
		 *
		 * @since   1.0.0
		 */
		protected function getLogger()
		{
			return new class {
				/**
				 * @param   string  $message   Warning text.
				 * @param   array   $context   Context.
				 *
				 * @return  void
				 */
				public function warning($message, array $context = []): void
				{
				}
			};
		}

		/**
		 * @param   string  $message  Message text.
		 * @param   string  $type     Message type.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function setMessage($message, $type = 'message'): void
		{
			if ($this->app !== null && method_exists($this->app, 'recordMessage'))
			{
				$this->app->recordMessage((string) $message, (string) $type);
			}
		}

		/**
		 * @param   string  $url  Redirect URL.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function setRedirect($url): void
		{
		}

		/**
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		protected function getRedirectUrlToList(): string
		{
			return 'index.php?option=com_wendfold&view=events';
		}

		/**
		 * @param   object        $model  Model.
		 * @param   array|null    $id     Deleted ids.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		protected function postDeleteHook($model, $id = null): void
		{
		}
	}
}

namespace Joomla\CMS\Language
{
	if (!\class_exists(Text::class, false))
	{
		/**
		 * Identity Text stand-in for list controller tests.
		 *
		 * @since  1.0.0
		 */
		class Text
		{
			/**
			 * @param   string  $string  Language key.
			 *
			 * @return  string
			 *
			 * @since   1.0.0
			 */
			public static function _($string): string // phpcs:ignore Joomla.NamingConventions.ValidFunctionName.MethodUnderscore
			{
				return (string) $string;
			}

			/**
			 * @param   string   $string  Language key.
			 * @param   integer  $count   Item count.
			 *
			 * @return  string
			 *
			 * @since   1.0.0
			 */
			public static function plural($string, $count): string
			{
				return (string) $string . ':' . (int) $count;
			}
		}
	}
}

namespace Joomla\Utilities
{
	if (!\class_exists(ArrayHelper::class, false))
	{
		/**
		 * ArrayHelper::getValue stand-in.
		 *
		 * @since  1.0.0
		 */
		class ArrayHelper
		{
			/**
			 * @param   array   $array    Source.
			 * @param   string  $name     Key.
			 * @param   mixed   $default  Default.
			 * @param   string  $type     Unused.
			 *
			 * @return  mixed
			 *
			 * @since   1.0.0
			 */
			public static function getValue($array, $name, $default = null, $type = '')
			{
				return \array_key_exists($name, $array) ? $array[$name] : $default;
			}
		}
	}
}

namespace Joomla\CMS\MVC\Model
{
	if (!\class_exists(AdminModel::class, false))
	{
		/**
		 * AdminModel stand-in so ListController instanceof checks pass in tests.
		 *
		 * @since  1.0.0
		 */
		class AdminModel
		{
		}
	}
}

namespace Wendfold\Tests\Support
{
	/**
	 * Application stand-in that records enqueueMessage calls.
	 *
	 * @since  1.0.0
	 */
	final class ListAppDouble
	{
		/** @var list<array{0: string, 1: string}> */
		public array $messages = [];

		/** @var ListInputDouble */
		private ListInputDouble $input;

		/** @var integer */
		private int $userId;

		/**
		 * @param   ListInputDouble  $input   Request input.
		 * @param   integer          $userId  Current user id for checkout checks.
		 *
		 * @since   1.0.0
		 */
		public function __construct(ListInputDouble $input, int $userId = 42)
		{
			$this->input = $input;
			$this->userId = $userId;
		}

		/**
		 * @return  ListInputDouble
		 *
		 * @since   1.0.0
		 */
		public function getInput(): ListInputDouble
		{
			return $this->input;
		}

		/**
		 * @return  object{id: int}
		 *
		 * @since   1.0.0
		 */
		public function getIdentity(): object
		{
			return (object) ['id' => $this->userId];
		}

		/**
		 * @param   string  $message  Message text.
		 * @param   string  $type     Message type.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function enqueueMessage($message, $type = 'message'): void
		{
			$this->recordMessage((string) $message, (string) $type);
		}

		/**
		 * @param   string  $message  Message text.
		 * @param   string  $type     Message type.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function recordMessage(string $message, string $type): void
		{
			$this->messages[] = [$message, $type];
		}
	}

	/**
	 * Input stand-in for cid / task.
	 *
	 * @since  1.0.0
	 */
	final class ListInputDouble
	{
		/** @var list<int> */
		private array $cid;

		/**
		 * @param   list<int>  $cid  Selected ids.
		 *
		 * @since   1.0.0
		 */
		public function __construct(array $cid)
		{
			$this->cid = $cid;
		}

		/**
		 * @param   string  $name     Variable name.
		 * @param   mixed   $default  Default.
		 * @param   string  $filter   Filter name.
		 *
		 * @return  mixed
		 *
		 * @since   1.0.0
		 */
		public function get($name, $default = null, $filter = 'cmd')
		{
			if ($name === 'cid')
			{
				return $this->cid;
			}

			return $default;
		}
	}

	/**
	 * Table stand-in for publish prune re-reads.
	 *
	 * @since  1.0.0
	 */
	final class ListTableDouble
	{
		/** @var integer */
		public int $id = 0;

		/** @var integer */
		public int $published = 0;

		/**
		 * Mirrors the table column name used by AdminModel::publish.
		 *
		 * @var    integer
		 * @since  1.0.0
		 */
		public int $checked_out = 0; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

		/**
		 * @param   integer|array  $keys   Primary key or key map.
		 * @param   boolean        $reset  Unused; mirrors Table::load.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function load($keys = null, $reset = true): bool
		{
			$pk = \is_array($keys) ? (int) ($keys['id'] ?? 0) : (int) $keys;
			$rows = $GLOBALS['wendfold_list_table_rows'] ?? [];

			if (!isset($rows[$pk]) || !\is_array($rows[$pk]))
			{
				return false;
			}

			$this->id = $pk;
			$this->published = (int) ($rows[$pk]['published'] ?? 0);
			$this->checked_out = (int) ($rows[$pk]['checked_out'] ?? 0); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

			return true;
		}

		/**
		 * @param   string  $fieldName  Logical field name.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function hasField($fieldName): bool
		{
			return $fieldName === 'checked_out' || $fieldName === 'published';
		}

		/**
		 * @param   string  $column  Logical column name.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public function getColumnAlias($column): string
		{
			return (string) $column;
		}
	}

	/**
	 * Model stand-in driven by test callbacks.
	 *
	 * @since  1.0.0
	 */
	final class ListModelDouble extends \Joomla\CMS\MVC\Model\AdminModel
	{
		/**
		 * @param   array    $pks    Primary keys.
		 * @param   integer  $value  Published value.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function publish(&$pks, $value = 1): bool
		{
			$callback = $GLOBALS['wendfold_list_publish_callback'] ?? null;
			$ok = true;

			if (\is_callable($callback))
			{
				$ok = (bool) $callback($pks, (int) $value);
			}

			if ($ok)
			{
				foreach ((array) $pks as $pk)
				{
					$pk = (int) $pk;

					if (!isset($GLOBALS['wendfold_list_table_rows'][$pk]))
					{
						$GLOBALS['wendfold_list_table_rows'][$pk] = [
							'published'   => (int) $value,
							'checked_out' => 0,
						];

						continue;
					}

					$GLOBALS['wendfold_list_table_rows'][$pk]['published'] = (int) $value;
				}
			}

			return $ok;
		}

		/**
		 * @param   array  $pks  Primary keys.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function delete(&$pks): bool
		{
			$callback = $GLOBALS['wendfold_list_delete_callback'] ?? null;

			if (!\is_callable($callback))
			{
				return true;
			}

			return (bool) $callback($pks);
		}

		/**
		 * @param   string  $name     Table name.
		 * @param   string  $prefix   Class prefix.
		 * @param   array   $options  Table options.
		 *
		 * @return  ListTableDouble
		 *
		 * @since   1.0.0
		 */
		public function getTable($name = '', $prefix = '', $options = [])
		{
			return new ListTableDouble;
		}

		/**
		 * @return  list<string>
		 *
		 * @since   1.0.0
		 */
		public function getErrors(): array
		{
			return $GLOBALS['wendfold_list_model_errors'] ?? [];
		}

		/**
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public function getError(): string
		{
			$errors = $this->getErrors();

			return $errors[0] ?? '';
		}
	}
}

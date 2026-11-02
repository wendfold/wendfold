<?php

/**
 * Wendfold - Stand-ins for the event save refusal test.
 *
 * EventModel is loaded for the audience, workflow, and RSVP refusal. These
 * classes supply the application, the language helper, and setError so that
 * path can run without Joomla.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Language;

if (!\class_exists(Text::class, false))
{
	/**
	 * Enough of Text for event validation, delete messages, and reminder mail tests.
	 *
	 * @since  1.0.0
	 */
	class Text
	{
		// phpcs:disable Joomla.NamingConventions.ValidFunctionName.MethodUnderscore -- mirrors Joomla Text

		/**
		 * Language keys this double can translate.
		 *
		 * @var    array<string, string>
		 * @since  1.0.0
		 */
		private const STRINGS = [
			'JLIB_FORM_VALIDATE_FIELD_INVALID'             => 'Invalid field: %s',
			'JLIB_FORM_VALIDATE_FIELD_REQUIRED'            => 'Field required: %s',
			'JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'  => 'Delete not permitted.',
			'COM_WENDFOLD_FIELD_AUDIENCE_SCOPE_LABEL'      => 'Audience',
			'COM_WENDFOLD_FIELD_WORKFLOW_MODE_LABEL'       => 'Workflow Mode',
			'COM_WENDFOLD_FIELD_ALLOW_RSVP_LABEL'          => 'Allow RSVP',
			'COM_WENDFOLD_FIELD_TITLE_LABEL'               => 'Title',
			'COM_WENDFOLD_FIELD_EVENT_DATE_LABEL'          => 'Event date',
			'COM_WENDFOLD_ATTENDANCE_MEMBER_FALLBACK'      => 'Member #%d',
			'COM_WENDFOLD_METRIC_LABEL_WITH_UNIT'          => '%s (%s)',
			'COM_WENDFOLD_METRIC_ENTRY_TITLE_VOLUNTEER_PREFIX' => 'Volunteer: ',
			'COM_WENDFOLD_METRIC_ENTRY_TITLE_MANUAL_ENTRY' => 'Manual entry',
			'COM_WENDFOLD_METRIC_ENTRY_TITLE_IMPORT'   => 'Imported entry',
			'COM_WENDFOLD_METRIC_ENTRY_TITLE_OTHER'    => 'Metric entry',
			'COM_WENDFOLD_MYACTIVITIES_NOTE_NONE'      => '—',
			'COM_WENDFOLD_UNIT_MILES'                      => 'miles',
			'COM_WENDFOLD_UNIT_MILES_1'                    => 'mile',
			'COM_WENDFOLD_UNIT_MILES_MORE'                 => 'miles',
			'COM_WENDFOLD_UNIT_EVENTS'                     => 'events',
			'COM_WENDFOLD_UNIT_EVENTS_1'                   => 'event',
			'COM_WENDFOLD_UNIT_EVENTS_MORE'                => 'events',
			'COM_WENDFOLD_UNIT_POINTS'                     => 'points',
			'COM_WENDFOLD_UNIT_POINTS_1'                   => 'point',
			'COM_WENDFOLD_UNIT_POINTS_MORE'                => 'points',
			'COM_WENDFOLD_UNIT_HOURS'                      => 'hours',
			'COM_WENDFOLD_UNIT_HOURS_1'                    => 'hour',
			'COM_WENDFOLD_UNIT_HOURS_MORE'                 => 'hours',
			'COM_WENDFOLD_ERROR_OPERATION_FAILED'            => 'Operation failed. Please try again.',
			'COM_WENDFOLD_ERROR_DELETE_EVENT_FAILED'         => 'Could not delete event "%s": %s',
			'COM_WENDFOLD_ERROR_EVENT_COULD_NOT_BE_DELETED'  => 'The event could not be deleted.',
			'COM_WENDFOLD_N_EVENTS_DELETED'                  => 'Deleted %d events.',
			'COM_WENDFOLD_N_EVENTS_DELETED_1'                => 'Deleted %d event.',
			'COM_WENDFOLD_ERROR_DELETE_EVENTS_PARTIAL'       => '%s Could not delete event "%s": %s',
			'PLG_TASK_WENDFOLD_EMAIL_RENEWAL_SUBJECT'       => 'Chapter Dues Renewal Reminder - %s Days',
			'PLG_TASK_WENDFOLD_EMAIL_RENEWAL_BODY'          => "Dear %s,\n\nYour chapter membership renews on %s (%s days).\n"
				. "Please contact your chapter secretary to renew.\n\nSee you there!",
			'PLG_TASK_WENDFOLD_EMAIL_EVENT_SUBJECT'         => 'Event Reminder: %s - Today!',
			'PLG_TASK_WENDFOLD_EMAIL_EVENT_BODY'            => "Hey %s!\n\nJust a reminder that today is %s.%s\n\nSee you there!",
			'COM_WENDFOLD_EMAIL_EVENT_REMINDER_LABEL'       => 'Reminder:',
			'COM_WENDFOLD_EMAIL_EVENT_CANCEL_SUBJECT'       => 'Event cancelled: %s',
			'COM_WENDFOLD_EMAIL_EVENT_CANCEL_BODY'          => "Hello %s,\n\nThe event \"%s\" on %s has been cancelled.\n"
				. "\n%s\n\nThis notice was sent by %s.",
		];

		/**
		 * Translate one key.
		 *
		 * @param   string  $string  Language key.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public static function _($string)
		{
			return self::STRINGS[$string] ?? (string) $string;
		}

		/**
		 * Translate a key, then fill its placeholders.
		 *
		 * @param   string  $string  Language key.
		 * @param   mixed   ...$args Values for the translated string.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public static function sprintf($string, ...$args)
		{
			return \sprintf(self::_($string), ...$args);
		}

		/**
		 * Choose the _1 form for a count of one, otherwise the base key.
		 *
		 * @param   string   $string  Language key stem.
		 * @param   integer  $n       Item count.
		 *
		 * @return  string
		 *
		 * @since   1.0.0
		 */
		public static function plural($string, $n)
		{
			$count = (int) $n;

			if ($count === 1)
			{
				$candidates = [$string . '_1', $string . '_ONE', $string];
			}
			elseif ($count === 0)
			{
				$candidates = [$string . '_0', $string];
			}
			else
			{
				$candidates = [
					$string . '_' . $count,
					$string . '_OTHER',
					$string . '_MORE',
					$string,
				];
			}

			$key = $string;

			foreach ($candidates as $candidate)
			{
				if (isset(self::STRINGS[$candidate]))
				{
					$key = $candidate;
					break;
				}
			}

			return \sprintf(self::_($key), $count);
		}
	}
	// phpcs:enable Joomla.NamingConventions.ValidFunctionName.MethodUnderscore
}

namespace Joomla\CMS\MVC\Model;

/**
 * Parent of EventModel, with the error the save path records.
 *
 * @since  1.0.0
 */
class AdminModel
{
	/**
	 * Errors passed to setError, oldest first.
	 *
	 * @var    array<int, string>
	 * @since  1.0.0
	 */
	private array $errors = [];

	/**
	 * Accept the config Joomla would pass and ignore it.
	 *
	 * @param   array  $config  Unused model config.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
	}

	/**
	 * Record the message a failed save shows.
	 *
	 * @param   string  $error  Message text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setError($error)
	{
		$this->errors[] = (string) $error;
	}

	/**
	 * Last recorded message.
	 *
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
	 * Data last handed to the parent save. Empty when the save was refused.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	public array $storedData = [];

	/**
	 * Record the fields a completed save would store.
	 *
	 * @param   array  $data  Event fields.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		$this->storedData = $data;
		$id = (int) ($data['id'] ?? 0);

		// Keep eventRows.status in sync so cancel-notice re-reads see the save.
		if ($id > 0
			&& \array_key_exists('status', $data)
			&& isset($this->database)
			&& \is_object($this->database)
			&& isset($this->database->eventRows[$id])
			&& (!isset($this->database->persistStatus) || $this->database->persistStatus))
		{
			$this->database->eventRows[$id]->status = (int) $data['status'];
		}

		return true;
	}

	/**
	 * Published values last handed to parent::publish, keyed by primary key.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $publishedValues = [];

	/**
	 * Primary keys last handed to parent::delete.
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $deletedIds = [];

	/**
	 * When true, canDelete returns false for every row (ACL denial).
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $denyDelete = false;

	/**
	 * Event ids for which canDelete returns false.
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $denyDeleteEventIds = [];

	/**
	 * When true, parent::delete returns false with no setError (plugin veto).
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $vetoDelete = false;

	/**
	 * Event ids for which parent::delete returns false (plugin veto).
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $vetoDeleteEventIds = [];

	/**
	 * Optional hook after parent::publish records the new value.
	 *
	 * @var    callable|null
	 * @since  1.0.0
	 */
	public $onPublish = null;

	/**
	 * When true, parent::publish returns false (plugin veto).
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failPublish = false;

	/**
	 * Primary keys pruned from publish while still returning true (ACL-style).
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $prunePublishIds = [];

	/**
	 * Optional hook after parent::delete records the primary keys.
	 *
	 * @var    callable|null
	 * @since  1.0.0
	 */
	public $onDelete = null;

	/**
	 * Record the published value a completed list action would store.
	 *
	 * @param   array    $pks    Primary keys.
	 * @param   integer  $value  New published value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function publish(&$pks, $value = 1)
	{
		if ($this->failPublish)
		{
			return false;
		}

		$kept = [];

		foreach ((array) $pks as $pk)
		{
			$pk = (int) $pk;

			if (\in_array($pk, $this->prunePublishIds, true))
			{
				continue;
			}

			$this->publishedValues[$pk] = (int) $value;
			$kept[] = $pk;
		}

		$pks = $kept;

		if (\is_callable($this->onPublish))
		{
			($this->onPublish)($pks, (int) $value);
		}

		return true;
	}

	/**
	 * ACL check used before parent::delete in EventModel::delete.
	 *
	 * @param   object  $record  Event row with id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	protected function canDelete($record)
	{
		if ($this->denyDelete)
		{
			return false;
		}

		$id = (int) ($record->id ?? 0);

		return !\in_array($id, $this->denyDeleteEventIds, true);
	}

	/**
	 * Record the primary keys a completed delete would remove.
	 *
	 * @param   array  $pks  Primary keys.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function delete(&$pks)
	{
		if ($this->vetoDelete)
		{
			return false;
		}

		foreach ((array) $pks as $pk)
		{
			$pk = (int) $pk;

			if (\in_array($pk, $this->vetoDeleteEventIds, true))
			{
				return false;
			}
		}

		foreach ((array) $pks as $pk)
		{
			$this->deletedIds[] = (int) $pk;
		}

		if (\is_callable($this->onDelete))
		{
			($this->onDelete)($pks);
		}

		return true;
	}

	/**
	 * State value the model reads after a completed save.
	 *
	 * @param   string|null  $key      Unused state key.
	 * @param   mixed        $default  Unused default.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getState($key = null, $default = null)
	{
		return 15;
	}

	/**
	 * Model name used to build the state key.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getName()
	{
		return 'event';
	}

	/**
	 * Check posted event fields the way Joomla's form validation does.
	 *
	 * The rules come from admin/forms/event.xml. A required field that was
	 * left out fails. An empty field that is not required is accepted, and
	 * its option list is not checked. A present value with validate="options"
	 * must be one of the listed choices.
	 *
	 * @param   object|null  $form   Unused. The event form XML is read directly.
	 * @param   array        $data   Posted fields.
	 * @param   string|null  $group  Unused field group.
	 *
	 * @return  array|false  The same fields when they pass, otherwise false.
	 *
	 * @since   1.0.0
	 */
	public function validate($form, $data, $group = null)
	{
		if (!\is_array($data))
		{
			$this->setError('Invalid form data.');

			return false;
		}

		$xml = \simplexml_load_file(\dirname(__DIR__, 2) . '/com_wendfold/admin/forms/event.xml');

		if ($xml === false)
		{
			$this->setError('The event form could not be read.');

			return false;
		}

		foreach ($xml->xpath('//field[@name]') as $field)
		{
			$name = (string) $field['name'];
			$required = \in_array(\strtolower((string) $field['required']), ['true', 'required', '1'], true);
			$present = \array_key_exists($name, $data);
			$value = $present ? $data[$name] : null;
			$empty = $value === null || $value === '';

			if ($empty && $required)
			{
				$label = \Joomla\CMS\Language\Text::_((string) $field['label']);
				$this->setError(\Joomla\CMS\Language\Text::sprintf('JLIB_FORM_VALIDATE_FIELD_REQUIRED', $label));

				return false;
			}

			if ($empty || (string) $field['validate'] !== 'options')
			{
				continue;
			}

			$allowed = [];

			foreach ($field->option as $option)
			{
				$allowed[] = (string) $option['value'];
			}

			$candidate = \is_scalar($value) ? (string) $value : null;

			if ($candidate === null || !\in_array($candidate, $allowed, true))
			{
				$label = \Joomla\CMS\Language\Text::_((string) $field['label']);
				$this->setError(\Joomla\CMS\Language\Text::sprintf('JLIB_FORM_VALIDATE_FIELD_INVALID', $label));

				return false;
			}
		}

		return $data;
	}
}

namespace Joomla\CMS;

/**
 * Application lookup for EventModel::save().
 *
 * @since  1.0.0
 */
class Factory
{
	/**
	 * Application returned by getApplication().
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	public static $application;

	/**
	 * Return the application installed for this test.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public static function getApplication()
	{
		return self::$application;
	}

	/**
	 * Build a date without booting Joomla.
	 *
	 * @param   string  $time  Date string or "now".
	 * @param   mixed   $tz    Zone name or DateTimeZone.
	 *
	 * @return  \Joomla\CMS\Date\Date
	 *
	 * @since   1.0.0
	 */
	public static function getDate($time = 'now', $tz = null)
	{
		return new \Joomla\CMS\Date\Date($time, $tz);
	}
}

namespace Wendfold\Tests\Double;

/**
 * Posted jform bag. get() returns the stored value, including a blank string.
 *
 * @since  1.0.0
 */
class InputBag
{
	/**
	 * @param   array  $data  Stored request values.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private array $data)
	{
	}

	/**
	 * Read one request value.
	 *
	 * @param   string  $name     Key.
	 * @param   mixed   $default  Value when the key is absent.
	 * @param   string  $filter   Unused filter name.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function get($name, $default = null, $filter = 'cmd')
	{
		if (!\array_key_exists($name, $this->data))
		{
			return $default;
		}

		return $this->data[$name];
	}
}

/**
 * Application input with a post bag and a files bag.
 *
 * @since  1.0.0
 */
class ApplicationInput
{
	/**
	 * @var    InputBag
	 * @since  1.0.0
	 */
	public $post;

	/**
	 * @var    InputBag
	 * @since  1.0.0
	 */
	public $files;

	/**
	 * @param   array  $post  Posted values, including jform.
	 *
	 * @since   1.0.0
	 */
	public function __construct(array $post)
	{
		$this->post  = new InputBag($post);
		$this->files = new InputBag([]);
	}
}

/**
 * Application whose post carries the raw jform for one save.
 *
 * @since  1.0.0
 */
class EventApplication
{
	/**
	 * @var    ApplicationInput
	 * @since  1.0.0
	 */
	private $input;

	/**
	 * @param   array  $jform  Raw jform fields.
	 *
	 * @since   1.0.0
	 */
	public function __construct(array $jform)
	{
		$this->input = new ApplicationInput(['jform' => $jform]);
	}

	/**
	 * Input used by the model.
	 *
	 * @return  ApplicationInput
	 *
	 * @since   1.0.0
	 */
	public function getInput()
	{
		return $this->input;
	}

	/**
	 * Signed-in user. The refusal path reads this and does not use it.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function getIdentity()
	{
		return (object) ['id' => 1];
	}
}

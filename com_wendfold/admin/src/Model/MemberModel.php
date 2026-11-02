<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin Model: Single Member (CRUD)
 * admin/src/Model/MemberModel.php
 *
 * Changelog:
 *   Phase 11 follow-up : chapter_join_date added to $dateFields
 *   Polish             : sponsor_id null-sanitized (empty string -> NULL for INT FK column)
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserFactoryInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Administrator\Helper\SaveDuplicateKey;

/**
 * MemberModel
 *
 * Handles single-record CRUD for #__wendfold_profiles.
 *
 * NULL-SANITIZE PATTERN
 * ---------------------
 * MySQL throws "Incorrect date value: ''" when PHP sends an empty string to a
 * DATE column, and "Incorrect integer value: ''" for INT FK columns like sponsor_id.
 *
 * - All nullable DATE/DATETIME columns are listed in $dateFields and looped in save().
 * - Nullable INT FK columns (sponsor_id) are handled individually in save().
 *
 * Add any new nullable DATE columns to $dateFields.
 * Add any new nullable INT FK columns to the FK block in save().
 */
class MemberModel extends AdminModel
{
	/**
	 * Cached check for whether the profiles table has a preferred_name column.
	 *
	 * @var boolean|null
	 */
	protected static $hasPreferredNameColumn = null;

	/**
	 * Check whether #__wendfold_profiles has preferred_name.
	 *
	 * @return boolean
	 */
	protected function profilesHavePreferredName(): bool
	{
		if (self::$hasPreferredNameColumn !== null)
		{
			return self::$hasPreferredNameColumn;
		}

		try
		{
			$cols = $this->getDatabase()->getTableColumns('#__wendfold_profiles', false);
			self::$hasPreferredNameColumn = isset($cols['preferred_name']);
		}
		catch (\Throwable $e)
		{
			self::$hasPreferredNameColumn = false;
		}

		return self::$hasPreferredNameColumn;
	}
	/**
	 * The type alias for this content type (used by content history).
	 *
	 * @var    string
	 */
	public $typeAlias = 'com_wendfold.member';

	/**
	 * Nullable DATE/DATETIME columns on #__wendfold_profiles.
	 *
	 * Any column listed here will be set to NULL (not empty string) when the
	 * submitted form value is empty. This prevents MySQL "Incorrect date value" errors.
	 *
	 * @var string[]
	 */
	protected $dateFields = [
		'member_since',
		'chapter_join_date',
		'renewal_date',
		'dues_paid_date',
		'waiver_signed_date',
		'national_verified_date',
		'national_sync_date',
		'reminder_60_sent',
		'reminder_30_sent',
	];

	/**
	 * Returns a Table object for #__wendfold_profiles.
	 *
	 * NOTE: prefix must be 'Administrator' to match the PSR-4 namespace segment
	 * Wendfold\Component\Wendfold\Administrator\Table\MemberTable.
	 * Using prefix='Table' causes "Table Profile not supported. File not found."
	 *
	 * @param   string  $name     Table class name suffix.
	 * @param   string  $prefix   Table class prefix.
	 * @param   array   $options  Configuration options.
	 *
	 * @return  Table
	 */
	public function getTable($name = 'Member', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * Returns the form for the member edit screen.
	 *
	 * @param   array    $data      Pre-populated form data.
	 * @param   boolean  $loadData  True to auto-load saved data.
	 *
	 * @return  \Joomla\CMS\Form\Form|boolean
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.member',
			'member',
			['control' => 'jform', 'load_data' => $loadData]
		);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Loads form data from the session stash or the database.
	 *
	 * @return  object  Data object with profile fields.
	 */
	protected function loadFormData()
	{
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_wendfold.edit.member.data', []);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $this->withRenewalReminderOptOut($this->withDefaultWaiverVersion($data));
	}

	/**
	 * Saves a member profile record.
	 *
	 * Null-sanitizes all nullable DATE/DATETIME columns and nullable INT FK
	 * columns before delegating to parent::save(), preventing MySQL
	 * "Incorrect value" errors when form fields are left blank.
	 *
	 * @param   array  $data  Raw POST data from the edit form.
	 *
	 * @return  boolean  True on success, false on failure.
	 */
	public function save($data)
	{
		// Defensive: dev installs may not yet have the preferred_name column.
		// Avoid SQL errors by dropping the field if the schema is not present.
		if (array_key_exists('preferred_name', $data) && !$this->profilesHavePreferredName())
		{
			unset($data['preferred_name']);
		}

		// Opt-out lives on #__users.params, not the profile row.
		$optOut = null;

		if (array_key_exists('renewal_reminders_optout', $data))
		{
			$optOut = (int) $data['renewal_reminders_optout'] === 1;
			unset($data['renewal_reminders_optout']);
		}

		// ---------------------------------------------------------------
		// Normalize common boolean-ish columns (TINYINT/INT NOT NULL)
		// so an untouched switch/radio never becomes an empty string.
		// ---------------------------------------------------------------
		$boolFields = ['published', 'dues_paid', 'waiver_signed', 'national_verified'];

		foreach ($boolFields as $field)
		{
			if (array_key_exists($field, $data))
			{
				if ($data[$field] === '' || $data[$field] === null)
				{
					$data[$field] = 0;
				}
				else
				{
					$data[$field] = (int) $data[$field];
				}
			}
		}

		// ---------------------------------------------------------------
		// Null-sanitize nullable DATE / DATETIME columns.
		// ---------------------------------------------------------------
		foreach ($this->dateFields as $field)
		{
			if (array_key_exists($field, $data) && $data[$field] === '')
			{
				$data[$field] = null;
			}
		}

		// ---------------------------------------------------------------
		// Normalize media/photo value.
		// Joomla's media field can include a "#joomlaImage::..." fragment.
		// Store only the path for stability across templates.
		// ---------------------------------------------------------------
		if (array_key_exists('photo', $data))
		{
			// IMPORTANT:
			// The profiles.photo column is VARCHAR NOT NULL.
			// Leaving the media field blank can submit an empty string; we
			// must never convert that to NULL or MySQL will throw:
			//   "Column 'photo' cannot be null".
			if ($data['photo'] === '' || $data['photo'] === null)
			{
				$data['photo'] = '';
			}
			elseif (is_string($data['photo']))
			{
				$path = trim($data['photo']);

				// Strip the optional "#joomlaImage::..." fragment.
				$path = explode('#', $path, 2)[0];

				// Normalise to a web path without a leading slash.
				$data['photo'] = ltrim($path, '/');
			}
			else
			{
				// Defensive: unexpected type
				$data['photo'] = '';
			}
		}

		// ---------------------------------------------------------------
		// Null-sanitize nullable INT FK columns.
		// An empty string sent to an INT column causes:
		//   "Incorrect integer value: '' for column `sponsor_id`"
		// ---------------------------------------------------------------
		$intFkFields = ['sponsor_id'];

		foreach ($intFkFields as $field)
		{
			if (array_key_exists($field, $data) && $data[$field] === '')
			{
				$data[$field] = null;
			}
		}

		// ---------------------------------------------------------------
		// Delegate to AdminModel::save().
		// ---------------------------------------------------------------
		try
		{
			$saved = parent::save($data);
		}
		catch (\Throwable $error)
		{
			$langKey = SaveDuplicateKey::memberLangKey($error);

			if ($langKey !== null)
			{
				$this->setError(Text::_($langKey));

				return false;
			}

			throw $error;
		}

		if ($saved === false)
		{
			$langKey = SaveDuplicateKey::memberLangKey((string) $this->getError());

			if ($langKey !== null)
			{
				$this->setError(Text::_($langKey));

				return false;
			}
		}

		if ($saved && $optOut !== null)
		{
			$userId = (int) ($data['user_id'] ?? 0);

			if ($userId > 0)
			{
				$this->persistRenewalReminderOptOut($userId, $optOut);
			}
		}

		return $saved;
	}

	/**
	 * Populate the form-only opt-out switch from the linked user params.
	 *
	 * @param   mixed  $data  Form data.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	private function withRenewalReminderOptOut($data)
	{
		$optOutField = RenewalReminderOptOut::FORM_FIELD;
		$userIdField = 'user_id';

		if (\is_array($data) && \array_key_exists($optOutField, $data))
		{
			return $data;
		}

		if (\is_object($data) && \array_key_exists($optOutField, get_object_vars($data)))
		{
			return $data;
		}

		$userId = 0;

		if (\is_object($data))
		{
			$attrs = get_object_vars($data);
			$userId = (int) ($attrs[$userIdField] ?? 0);
		}
		elseif (\is_array($data))
		{
			$userId = (int) ($data[$userIdField] ?? 0);
		}

		$optedOut = false;

		if ($userId > 0)
		{
			try
			{
				/** @var UserFactoryInterface $users */
				$users = Factory::getContainer()->get(UserFactoryInterface::class);
				$user  = $users->loadUserById($userId);
				$optedOut = RenewalReminderOptOut::isOptedOut($user);
			}
			catch (\Throwable $e)
			{
				$optedOut = false;
			}
		}

		return RenewalReminderOptOut::fillFormDataIfMissing($data, $optedOut);
	}

	/**
	 * Persist the opt-out flag on the linked Joomla user.
	 *
	 * @param   integer  $userId  User id.
	 * @param   boolean  $optOut  Opt-out value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function persistRenewalReminderOptOut(int $userId, bool $optOut): void
	{
		try
		{
			/** @var UserFactoryInterface $users */
			$users = Factory::getContainer()->get(UserFactoryInterface::class);
			$user  = $users->loadUserById($userId);
			RenewalReminderOptOut::setOptedOut($user, $optOut);
		}
		catch (\Throwable $e)
		{
			// Profile save already succeeded; opt-out write failures are non-fatal.
		}
	}

	/**
	 * Fill a blank waiver version from the component option.
	 *
	 * @param   mixed  $data  Form data.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	private function withDefaultWaiverVersion($data)
	{
		$waiverVersion = 'waiver_version';
		$configured = ComponentHelper::getParams('com_wendfold')->get('waiver_version', '');

		if (\is_object($data))
		{
			$data->$waiverVersion = ComponentOptions::waiverVersionForRecord(
				(int) ($data->id ?? 0),
				$data->$waiverVersion ?? '',
				$configured
			);

			return $data;
		}

		if (\is_array($data))
		{
			$data[$waiverVersion] = ComponentOptions::waiverVersionForRecord(
				(int) ($data['id'] ?? 0),
				$data[$waiverVersion] ?? '',
				$configured
			);
		}

		return $data;
	}
}

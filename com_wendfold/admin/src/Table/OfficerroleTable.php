<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupRecordRules;

/**
 * Officer role lookup.
 *
 * @since  1.0.0
 */
class OfficerroleTable extends LookupTable
{
	/**
	 * @param   DatabaseDriver  $db  Database connector.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->usageTable = '#__wendfold_officers';
		$this->usageColumn = 'role_id';

		parent::__construct($db);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_officer_roles';
	}

	/**
	 * Title is required. system_key is empty or a stable token, and unique.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		$systemKey = 'system_key';
		$key = LookupRecordRules::systemKey($this->$systemKey ?? null);

		if (!$key['ok'])
		{
			$this->setError(Text::_($key['message']));

			return false;
		}

		$this->$systemKey = $key['systemKey'];

		if ($this->$systemKey !== null && $this->systemKeyTaken((string) $this->$systemKey, (int) $this->id))
		{
			$this->setError(Text::_('COM_WENDFOLD_OFFICER_ROLE_SYSTEM_KEY_TAKEN'));

			return false;
		}

		return parent::check();
	}

	/**
	 * Whether another role already uses this key.
	 *
	 * @param   string   $systemKey  Normalised key.
	 * @param   integer  $id         This row, excluded from the search.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function systemKeyTaken(string $systemKey, int $id): bool
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_officer_roles'))
			->where($db->quoteName('system_key') . ' = :systemKey')
			->where($db->quoteName('id') . ' <> :roleId')
			->bind(':systemKey', $systemKey)
			->bind(':roleId', $id, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult() > 0;
	}
}

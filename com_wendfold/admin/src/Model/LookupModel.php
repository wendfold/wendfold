<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupDeleteGuard;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;

/**
 * Single-record model for a chapter lookup.
 *
 * Delete is refused while another table still references the row.
 *
 * @since  1.0.0
 */
abstract class LookupModel extends AdminModel
{
	/**
	 * Table that references this lookup, including the #__ prefix.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	abstract protected function usageTable(): string;

	/**
	 * Column on that table that stores the lookup id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	abstract protected function usageColumn(): string;

	/**
	 * Context key for the edit form's session data.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	abstract protected function editContext(): string;

	/**
	 * How many rows reference this lookup id.
	 *
	 * @param   integer  $id  Lookup id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function countUsage(int $id): int
	{
		if ($id < 1)
		{
			return 0;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName($this->usageTable()))
			->where($db->quoteName($this->usageColumn()) . ' = :lookupId')
			->bind(':lookupId', $id, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Delete only the rows that nothing else uses.
	 *
	 * The first row that is still referenced stops the batch and names that row.
	 *
	 * @param   array  $pks  Primary keys.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function delete(&$pks)
	{
		$pks = (array) $pks;
		$table = $this->getTable();

		foreach ($pks as $pk)
		{
			if (!$table->load((int) $pk))
			{
				continue;
			}

			$count = $this->countUsage((int) $table->id);
			$message = LookupDeleteGuard::messageKey($count);

			if ($message !== null)
			{
				$this->setError(
					Text::sprintf(
						$message,
						LookupTitle::display((string) $table->title),
						$count
					)
				);

				return false;
			}
		}

		return parent::delete($pks);
	}

	/**
	 * Session data from a failed save, otherwise the stored row.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		/** @var \Joomla\CMS\Application\CMSApplication $app */
		$app = Factory::getApplication();
		$data = $app->getUserState($this->editContext(), []);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}
}

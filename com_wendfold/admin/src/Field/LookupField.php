<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;

/**
 * A dropdown of published lookup rows, with the current row included.
 *
 * Titles that are language keys are translated. Event types carry
 * data-behavior so the event form can show the fields that match.
 *
 * @since  1.0.0
 */
class LookupField extends ListField
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'Lookup';

	/**
	 * Options for the three lookups.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	protected function getOptions()
	{
		$options = parent::getOptions();
		$spec = $this->spec();

		if ($spec === null)
		{
			return $options;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$current = (int) $this->value;
		$query = $db->createQuery()
			->select($spec['columns'])
			->from($db->quoteName($spec['table']))
			->where('(' . $db->quoteName('published') . ' = 1 OR ' . $db->quoteName('id') . ' = :currentId)')
			->bind(':currentId', $current, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');

		$rows = $db->setQuery($query)->loadObjectList() ?: [];

		foreach ($rows as $row)
		{
			$option = HTMLHelper::_(
				'select.option',
				(string) $row->id,
				LookupTitle::display((string) $row->title)
			);

			if (isset($row->behavior))
			{
				$riding = 'default_riding';
				$workflow = 'default_workflow_mode';
				$option->optionattr = 'data-behavior="' . (int) $row->behavior
					. '" data-default-riding="' . (int) ($row->$riding ?? 0)
					. '" data-default-workflow="' . (int) ($row->$workflow ?? 1) . '"';
			}

			$options[] = $option;
		}

		return $options;
	}

	/**
	 * Table and columns for the lookup attribute.
	 *
	 * @return  array{table: string, columns: string[]}|null
	 *
	 * @since   1.0.0
	 */
	private function spec(): ?array
	{
		return match ((string) $this->element['lookup']) {
			'membershiptype' => [
				'table'   => '#__wendfold_membership_types',
				'columns' => ['id', 'title', 'ordering'],
			],
			'officerrole' => [
				'table'   => '#__wendfold_officer_roles',
				'columns' => ['id', 'title', 'ordering'],
			],
			'eventtype' => [
				'table'   => '#__wendfold_event_types',
				'columns' => ['id', 'title', 'behavior', 'default_riding', 'default_workflow_mode', 'ordering'],
			],
			default => null,
		};
	}
}

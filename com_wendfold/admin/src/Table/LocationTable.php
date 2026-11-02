<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\Table;
defined('_JEXEC') or die;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Factory;

class LocationTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $_supportNullValue = true; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.ClassVarHasUnderscore

	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__wendfold_locations', 'id', $db);
		$this->setColumnAlias('published', 'published');
	}

	public function check(): bool
	{
		if (empty($this->name))
		{
			$this->setError('Location name is required.');

			return false;
		}

		return parent::check();
	}

	public function store($updateNulls = true): bool
	{
		$user = Factory::getApplication()->getIdentity();
		$date = Factory::getDate()->toSql();

		if (!$this->id)
		{
			$this->created    = $date;
			$this->created_by = $user->id;
		}

		$this->modified    = $date;
		$this->modified_by = $user->id;

		// The form still posts state and zip. Copy them into the target columns.
		if (isset($this->state))
		{
			$this->region = (string) $this->state;
		}

		if (isset($this->zip))
		{
			$this->postcode = (string) $this->zip;
		}

		return parent::store($updateNulls);
	}
}

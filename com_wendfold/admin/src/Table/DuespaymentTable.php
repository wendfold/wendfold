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

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class DuespaymentTable extends Table
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
		parent::__construct('#__wendfold_dues_payments', 'id', $db);
	}

	public function check(): bool
	{
		if (empty($this->profile_id))
		{
			$this->setError('Member is required.');

			return false;
		}

		if (empty($this->year))
		{
			$this->setError('Year is required.');

			return false;
		}

		if ($this->amount_paid < 0)
		{
			$this->setError('Amount must be zero or greater.');

			return false;
		}

		return parent::check();
	}

	/**
	 * Fill the target period and amount columns from the legacy year and amount.
	 *
	 * The dues screen still reads and writes year and amount_paid. period_start,
	 * period_end, amount, and the audit timestamps have no database default.
	 *
	 * @param   boolean  $updateNulls  True to update null fields.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function store($updateNulls = true): bool
	{
		$date = Factory::getDate()->toSql();
		$user = Factory::getApplication()->getIdentity();
		$createdBy = 'created_by';
		$modifiedBy = 'modified_by';
		$periodStart = 'period_start';
		$periodEnd = 'period_end';
		$amountPaid = 'amount_paid';
		$paymentDate = 'payment_date';

		if (empty($this->id))
		{
			if (empty($this->created))
			{
				$this->created = $date;
			}

			$this->$createdBy = (int) $user->id;
		}

		$this->modified = $date;
		$this->$modifiedBy = (int) $user->id;

		$year = (int) $this->year;

		if ($year > 0)
		{
			$this->$periodStart = sprintf('%04d-01-01', $year);
			$this->$periodEnd = sprintf('%04d-12-31', $year);
		}

		if ($this->$amountPaid !== null && $this->$amountPaid !== '')
		{
			$this->amount = $this->$amountPaid;
		}

		if (!isset($this->$paymentDate) || $this->$paymentDate === '')
		{
			$this->$paymentDate = substr($date, 0, 10);
		}

		if ($this->published === null || $this->published === '')
		{
			$this->published = 1;
		}

		return parent::store($updateNulls);
	}
}

<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * Wendfold - Chapter Status Report Model
 *
 * Returns members whose renewal_date falls within a 60-day window:
 *   - expired up to 30 days ago, OR
 *   - expiring within the next 30 days
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

class StatusreportModel extends BaseDatabaseModel
{
	/**
	 * Membership type map
	 */
	/**
	 * Get members in the expiry window (expired 30 days ago through expiring 30 days from now).
	 *
	 * @return  array
	 */
	public function getExpiringMembers(): array
	{
		$db = $this->getDatabase();

		$siteTime = SiteTime::fromApplication();
		$past30   = $siteTime->shiftDays(-30);
		$future30 = $siteTime->shiftDays(30);

		$query = $db->createQuery()
			->select([
				'p.id',
				'p.member_number',
				'mt.title AS membership_type_title',
				'p.renewal_date',
				'p.national_verified',
				'p.reminder_60_sent',
				'p.reminder_30_sent',
				'u.name AS member_name',
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin(
				$db->quoteName('#__users', 'u')
				. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->leftJoin(
				$db->quoteName('#__wendfold_membership_types', 'mt')
				. ' ON ' . $db->quoteName('mt.id') . ' = ' . $db->quoteName('p.membership_type_id')
			)
			->where($db->quoteName('p.renewal_date') . ' >= ' . $db->quote($past30))
			->where($db->quoteName('p.renewal_date') . ' <= ' . $db->quote($future30))
			->where($db->quoteName('p.published') . ' = 1')
			->order($db->quoteName('p.renewal_date') . ' ASC');

		$rows = $db->setQuery($query)->loadObjectList() ?: [];

		// Decorate each row with human-readable values
		foreach ($rows as $row)
		{
			$row->membership_type_label = \Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle::display((string) ($row->membership_type_title ?? '')); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		}

		return $rows;
	}

	/**
	 * Determine Bootstrap row class for a renewal date string.
	 *
	 * @param   string  $renewalDate  Y-m-d
	 * @return  string  'table-danger' | 'table-warning' | ''
	 */
	public static function getRowClass(string $renewalDate): string
	{
		$today = SiteTime::fromApplication()->today();

		if ($renewalDate < $today)
		{
			return 'table-danger';
		}

		return 'table-warning';
	}
}

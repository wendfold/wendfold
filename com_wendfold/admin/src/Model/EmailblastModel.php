<?php
/**
 * Wendfold - Email Blast Model
 *
 * Builds the recipient list for an outgoing email blast.
 * Joins #__wendfold_profiles to #__users to get email addresses.
 *
 * Audience values match the compose form:
 *   all           - all published members with a user account
 *   dues_paid     - published members with dues_paid = 1
 *   dues_unpaid   - published members with dues_paid = 0
 *   expiring_60   - renewal_date within the next 60 days (not yet expired)
 *   officers      - published members with a current published officer row
 *
 * Schema verified against install.mysql.utf8.sql:
 *   profiles: id, user_id (NOT NULL), renewal_date (DATE NULL), published
 *   #__users: id, name, email
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Wendfold\Component\Wendfold\Administrator\Helper\EmailblastHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

class EmailblastModel extends BaseDatabaseModel
{
	/** Audiences offered by the compose form. */
	public const AUDIENCES = ['all', 'dues_paid', 'dues_unpaid', 'expiring_60', 'officers'];

	/**
	 * Return an array of recipient objects for the given audience.
	 *
	 * Each object has:
	 *   - id    (int)     Profile id from #__wendfold_profiles
	 *   - name  (string)  Member's display name from #__users
	 *   - email (string)  Member's email address from #__users
	 *
	 * @param   string  $audience  One of self::AUDIENCES. Anything else is treated as all.
	 *
	 * @return  object[]
	 */
	public function getRecipients(string $audience = 'all'): array
	{
		if (!EmailblastHelper::canSend())
		{
			return [];
		}

		$db = $this->getDatabase();

		$query = $db->createQuery()
			->select($db->quoteName(['p.id', 'u.name', 'u.email']))
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'INNER',
				$db->quoteName('#__users', 'u')
				. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('p.published') . ' = 1')
			->where($db->quoteName('p.user_id') . ' IS NOT NULL')
			->where($db->quoteName('u.email') . ' != ' . $db->quote(''))
			->order($db->quoteName('u.name') . ' ASC');

		if (!in_array($audience, self::AUDIENCES, true))
		{
			$audience = 'all';
		}

		switch ($audience)
		{
			case 'dues_paid':
				$query->where($db->quoteName('p.dues_paid') . ' = 1');
				break;

			case 'dues_unpaid':
				$query->where($db->quoteName('p.dues_paid') . ' = 0');
				break;

			case 'expiring_60':
				$siteTime = SiteTime::fromApplication();
				$renewalFrom = $siteTime->today();
				$renewalTo = $siteTime->shiftDays(60);
				$query->where($db->quoteName('p.renewal_date') . ' IS NOT NULL');
				$query->where($db->quoteName('p.renewal_date') . ' >= :renewalFrom')->bind(':renewalFrom', $renewalFrom);
				$query->where($db->quoteName('p.renewal_date') . ' <= :renewalTo')->bind(':renewalTo', $renewalTo);
				break;

			case 'officers':
				$officer = $db->createQuery()
					->select('1')
					->from($db->quoteName('#__wendfold_officers', 'o'))
					->where($db->quoteName('o.profile_id') . ' = ' . $db->quoteName('p.id'))
					->where($db->quoteName('o.published') . ' = 1')
					->where($db->quoteName('o.is_current') . ' = 1');
				$query->where('EXISTS (' . $officer . ')');
				break;

			case 'all':
			default:
				break;
		}

		return $db->setQuery($query)->loadObjectList() ?: [];
	}
}

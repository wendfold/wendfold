<?php
/**
 * Wendfold - Site Volunteer Board Model
 *
 * Reuses the single-event model so volunteer management stays on the
 * existing event schema and permission layer.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class VolunteerboardModel extends EventModel
{
	protected function populateState(): void
	{
		$input = Factory::getApplication()->getInput();
		$id = $input->getInt('event_id', 0);

		if ($id <= 0)
		{
			$id = $input->getInt('id', 0);
		}

		$this->setState('event.id', $id);
	}
}

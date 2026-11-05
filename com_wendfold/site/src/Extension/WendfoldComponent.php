<?php

/**
 * Wendfold - Chapter Management System
 * Site Component Extension Class
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

class WendfoldComponent extends MVCComponent
{
	use RouterServiceTrait;

	public function boot(CMSApplicationInterface $app): void
	{
		// Language files are now properly split:
		//   /language/en-GB/com_wendfold.ini      <- site strings (auto-loaded by Joomla)
		//   /administrator/language/en-GB/com_wendfold.ini <- admin strings
		//
		// No manual load needed here. Joomla auto-loads the site .ini
		// because the manifest declares it in the top-level <languages> block.
	}
}

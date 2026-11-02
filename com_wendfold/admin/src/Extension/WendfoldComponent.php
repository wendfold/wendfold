<?php

/**
 * Wendfold  -  Chapter Management System
 * Administrator Component Extension Class
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Psr\Container\ContainerInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\AdminBrand;

/**
 * Administrator entry for com_wendfold.
 *
 * Joomla only calls boot() when BootableExtensionInterface is implemented
 * (see ExtensionManagerTrait). That is required for admin brand chrome.
 *
 * @since  1.0.0
 */
class WendfoldComponent extends MVCComponent implements BootableExtensionInterface, RouterServiceInterface
{
	use RouterServiceTrait;

	/**
	 * Boot the component: admin language and brand chrome.
	 *
	 * Brand assets and toolbar-title injection run only for the administrator
	 * HTML client. Site and CLI boots return without side effects.
	 *
	 * @param   ContainerInterface  $container  The extension DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function boot(ContainerInterface $container): void
	{
		$app = Factory::getApplication();

		// Site, CLI, and API boots must not register admin HTML chrome.
		if (!$app->isClient('administrator'))
		{
			return;
		}

		$app->getLanguage()->load(
			'com_wendfold',
			JPATH_ADMINISTRATOR
		);

		$app->getDispatcher()->addListener(
			'onBeforeRender',
			static function () use ($app): void {
				if ($app->getInput()->getCmd('option') !== 'com_wendfold')
				{
					return;
				}

				AdminBrand::ensureAssets();

				// @deprecated 5.2.0 still used by mod_title through Joomla 6.
				if (empty($app->JComponentTitle) || !is_string($app->JComponentTitle))
				{
					return;
				}

				$app->JComponentTitle = AdminBrand::injectMarkIntoToolbarTitle($app->JComponentTitle);
			}
		);
	}
}
